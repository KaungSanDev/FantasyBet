<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FixtureController extends Controller
{
    private $apiKey = "250b6609c4d94da5989532115ee27a24";
    
    public function getFixtureFromApi()
    {
        //if no cache 
        if(cache('date') == false){
            Cache::put('date',date('Y-m-d',strtotime("-1 days")));
            $dateFrom = new DateTimeImmutable("now",new DateTimeZone('Asia/Yangon'));
            $dateTo = $dateFrom->add(DateInterval::createFromDateString('7 days'));
        }else{
            $strToTime = strtotime(cache('date')); //string cache date to time
            $dateFormat = date('Y-m-d', $strToTime);//format it in Y-m-d
            $dateFrom = new DateTimeImmutable($dateFormat,new DateTimeZone('Asia/Yangon')); //convert to addable day format
            $dateTo = $dateFrom->add(DateInterval::createFromDateString('7 days'));
        }
        $response =Http::withHeaders(['X-Auth-Token' => $this->apiKey,])->get('https://api.football-data.org/v2/matches',[
            'competitions' => '2021',//competitions id for PremierLeague
            'dateFrom' => $dateFrom->format('Y-m-d'), //this will be yesterdays's date when method called for second time
            'dateTo' => $dateTo->format('Y-m-d'),
            ]);
            $responseObject = $response->object();
            $matches = $responseObject->matches;
            if($matches != null){
                foreach ($matches as $match) 
                {
                    Fixture::updateOrInsert(
                        [//Query Filter
                            'matchday' => $match->matchday,
                            'homeTeam' => $match->homeTeam->name,
                            'awayTeam' => $match->awayTeam->name,],
                            [
                                'time' => $match->utcDate,
                                'result' => $match->score->fullTime->homeTeam.'-'.$match->score->fullTime->awayTeam,
                                'status' => $match->status,
                                'winner' => $match->score->winner,
                                ]);
                            }
                        }
                        Cache::put('date', $dateFrom->format('Y-m-d'));    
    }
                    
                    /**
                    * Display a listing of the resource.
                    *
                    * @return \Illuminate\Http\Response
                    */
                    public function index()
                    {
                        //
                        $fixture = Fixture::all();
                        return view('admin.fixture', compact('fixture'));
                    }
                    
                    /**
                    * Show the form for creating a new resource.
                    *
                    * @return \Illuminate\Http\Response
                    */
                    public function create()
                    {
                        //
                    }
                    
                    /**
                    * Store a newly created resource in storage.
                    *
                    * @param  \Illuminate\Http\Request  $request
                    * @return \Illuminate\Http\Response
                    */
                    public function store(Request $request)
                    {
                        //
                        Fixture::create([
                            'matchday' => $request->matchday,
                            'homeTeam' => $request->homeTeam,
                            'awayTeam' => $request->awayTeam,
                            'time'  => $request->time,
                            'result' =>"Vs",
                            'status' => "UpComing",
                            'winner' => "Unknown"
                            ]);
                            return back();
                        }
                        
                        /**
                        * Display the specified resource.
                        *
                        * @param  int  $id
                        * @return \Illuminate\Http\Response
                        */
                        public function show($id)
                        {
                            //
                        }
                        
                        /**
                        * Show the form for editing the specified resource.
                        *
                        * @param  int  $id
                        * @return \Illuminate\Http\Response
                        */
                        public function edit($id)
                        {
                            $data = Fixture::where('id', $id)->first();
                            $data->status ="Ongoing";
                            $data->save();
                            return back();
                        }
                        
                        /**
                        * Update the specified resource in storage.
                        *
                        * @param  \Illuminate\Http\Request  $request
                        * @param  int  $id
                        * @return \Illuminate\Http\Response
                        */
                        public function update(Request $request)
                        {
                            //
                            $id = $request->id;
                            $data = Fixture::where('id', $id)->first();
                            $data->result = $request->team1_goal." | ".$request->team2_goal;
                            $data->winner = $request->winner;
                            $data->status = "Finished";
                            $data->save();
                            
                            if ($request->winner != "Draw Match"){
                                $point = "none";
                                if ($data->winner == $data->homeTeam) {
                                    $point = $data->team1_point;
                                } elseif ($data->winner == $data->awayTeam) {
                                    $point = $data->team2_point;
                                }
                                return redirect()->route('betresult', ['id' => $data->id, 'winner' => $data->winner, 'point' => $point]);
                            }
                        }
                        
                        /**
                        * Remove the specified resource from storage.
                        *
                        * @param  int  $id
                        * @return \Illuminate\Http\Response
                        */
                        public function destroy($id)
                        {
                            $fixture = Fixture::findOrFail($id);
                            $fixture->delete();
                        }
                    }
                    