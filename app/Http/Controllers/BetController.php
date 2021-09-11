<?php

namespace App\Http\Controllers;

use App\Http\Traits\HasTeams;
use Illuminate\Http\Request;
use App\Models\Fixture;
use App\Models\User;
use App\Models\Bet;
use Illuminate\Support\Facades\Auth;

class BetController extends Controller
{   
    use HasTeams;
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function trybet(Request $request)
    {
        $user = Auth::user()->id;
        Bet::create([
            'match_id' => $request->match_id,
            'winner' => $request->winner,
            'supporter' => $user,
            'amount' => $request->betamount,
            'current_point' => $this->getCurrentPointForTeam($request->match_id,$request->winner),
            'paid' => false,
        ]);

        $this->coinReduction($user, $request->betamount);
        $this->rankAdd($user, "justbet");
        $this->odd_cal($request->match_id, $request->winner);
        return redirect()->route('home')->with('success','Your bet is placed. Good Luck !');
    }
    // saved current point with each bet.
    // allow user to get their x times of point when their bet is submited
    // not the one that lastly update by odd_cal method;
    public function getCurrentPointForTeam($match_id,$team){
        $match = Fixture::findOrFail($match_id);
        if($match->home_team == $team){
            return $match->home_team_point;
        }else{
            return $match->away_team_point;
        }
    }
    public function odd_cal($id, $team){
        $temp1 = Bet::where('match_id', $id)->where('winner', $team)->get();
        $temp2 = Bet::where('match_id', $id)->get();
        $supporter_no_1 = $temp1->count();
        $total_no = $temp2->count();
        $supporter_no_2 = $total_no - $supporter_no_1;
        if ($supporter_no_2 <=0) {
            $supporter_no_2 = 1;
        }
        $data = Fixture::where('id', $id)->first();
        if ($data->home_team == $team) {
            $data->home_team_point -= (($supporter_no_2/100)+0.2);
            $data->away_team_point += (($supporter_no_1/100)+0.5);
        } elseif ($data->away_team == $team) {
            $data->home_team_point += (($supporter_no_2/100)+0.5);
            $data->away_team_point -= (($supporter_no_1/100)+0.2);
        }
        // set minimum point
        if ($data->home_team_point <=0) {
            $data->home_team_point = 0.3;
        }
        if ($data->away_team_point <=0) {
            $data->away_team_point = 0.3;
        }
        $data->save();
    }
    public function addcoin($id, $point, $betamount){
        
        $user = User::where('id', $id)->first();
        $coin = round($betamount* $point);
        $user->coin += ($coin + $betamount);
        $user->save();
        $condition="betwin";
        $this->rankAdd($id, $condition);
    }
    public function coinReduction($user, $coin)
    {
        $data = User::where('id', $user)->first();
        $data->coin -= $coin;
        $data->save();
    }
    public function rankAdd($user, $condition)
    {
        $user = User::where('id', $user)->first();
        if ($condition =="justbet") {
            $user->rank_no +=2;
        } elseif ($condition == "betwin") {
            $user->rank_no +=5;
        }
        $user->save();
    }
    public function updateBetResult(Fixture $match){
        $winner = null;
        // decide who win the game
        if($match->home_team_score > $match->away_team_score){
            $winner = $match->home_team;
        }else if($match->home_team_score < $match->away_team_score){
            $winner = $match->away_team;
        }
        // Eloquent relationship/ fixture hasMany bets
        foreach ($match->bets as $each_bet) {
            // unpaid and bet for winning team only
            // unpaid and bet for losing will be excluded
            if($each_bet->paid == false && $winner == $each_bet->winner){
                //Adding Coin for winner
                $user = User::where('id', $each_bet->supporter)->first();
                $coin = round($each_bet->amount * $each_bet->current_point);
                $user->coin += ($coin + $each_bet->amount);
                //Add rank
                $user->rank_no +=5;
                $user->save();
                // Paid true
                $each_bet->paid = true;
                $each_bet->save();
            }
        }
    }
}
