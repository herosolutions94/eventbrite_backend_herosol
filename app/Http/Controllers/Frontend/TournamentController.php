<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\TournamentImage;
use App\Models\TournamentCategory;
use App\Models\Category;
use App\Models\TournamentType;
use App\Models\EventType;
use App\Models\Country;
use App\Models\NumberOfTeam;
use App\Models\TournamentFormat;
use App\Models\TournamentLevel;
use App\Models\User;
use App\Models\TournamentRounds;
use App\Models\TournamentMatches;
use App\Http\Requests\StoreTournamentRequest;
use Stripe\StripeClient;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class TournamentController extends Controller
{
    public function getCategories(Request $request){
        $search = $request->search;
        $categories = Category::where('name', 'like', '%'.$search.'%')->get();
        return response()->json(['data' => $categories], 200);
    }
    public function tournamentsByUser(Request $request){
        
        $tournaments = Tournament::with([
            'images', 
            'tournamentCategories',
            'category',
            'teams.teamMembers',
            'reviews.user',
            'tournamentType',
            ])
        ->where('is_active', 1)
        ->where('user_id', $request->user_id)
        ->latest()
        ->paginate(10);
        return response()->json(['data' => $tournaments], 200);
    }
    public function tournamentDetail(Request $request){
        
        $tournament = Tournament::with([
            'images', 
            'tournamentCategories',
            'category',
            'teams.teamMembers',
            'reviews.user',
            'tournamentType',
            ])
        ->where('is_active', 1)
        ->where('id', $request->id)
        ->first();

        $teamsCount = $tournament->teams->count();
        if($tournament->inProgressRound){
            $tournament->in_progress_round = $tournament->inProgressRound;
        }
        // $tournament->countries=Country::all();
        
        if($tournament->match_type=='single'){
            $tournament->rounds = $tournament->rounds;
            foreach($tournament->rounds as $round){
                $round->matches=$round->matches;
                foreach($round->matches as $match){
                    $match->team_1=$match->team_1;
                    $match->team_2=$match->team_2;
                    $match->winner_row=$match->winner_row;
                    $match->looser_row=$match->looser_row;
                }
            }
            $latestCompletedRound = TournamentRounds::where('tournament_id', $tournament->id)
                ->where('status', 'completed')
                ->latest()
                ->first();
            
            
             $pendingMatchesArr=[];
            if($latestCompletedRound){
               
                $completedMatches=TournamentMatches::where(['round_id'=>$latestCompletedRound->id,'status'=>1])->get();
                if(count($completedMatches) > 0){
                    foreach($completedMatches as $completedMatche){
                        $pendingMatchesArr[]=$completedMatche->winner;
                    }
                }
            }
            if(!empty($tournament->available_teams)){
                    if (strpos($tournament->available_teams, ',') !== false) {
                        $availableTeamsArr = explode(',', $tournament->available_teams);
                        $pendingMatchesArr=array_merge($pendingMatchesArr, $availableTeamsArr);
                    } else {
                        $pendingMatchesArr[]=$tournament->available_teams;
                    }
            }
            $tournament->pending_teams=$pendingMatchesArr;
            $tournament->latestCompletedRound=$latestCompletedRound;
        }
        else if($tournament->match_type=='double'){
            if($final_round = TournamentRounds::where('tournament_id', $tournament->id)
                ->where('status', 'in_progress')
                ->where('team_type', 'final')
                ->latest()
                ->first()){
                $final_round->matches=$final_round->matches;
                foreach($final_round->matches as $final_round_match){
                    $final_round_match->team_1=$final_round_match->team_1;
                    $final_round_match->team_2=$final_round_match->team_2;
                    $final_round_match->winner_row=$final_round_match->winner_row;
                    $final_round_match->looser_row=$final_round_match->looser_row;
                }
                $tournament->final_match_round_obj=$final_round; 
            }
            else if($final_completed_round = TournamentRounds::where('tournament_id', $tournament->id)
                ->where('status', 'completed')
                ->where('team_type', 'final')
                ->latest()
                ->first()){
                $final_completed_round->matches=$final_completed_round->matches;
                foreach($final_completed_round->matches as $final_round_match){
                    $final_round_match->team_1=$final_round_match->team_1;
                    $final_round_match->team_2=$final_round_match->team_2;
                    $final_round_match->winner_row=$final_round_match->winner_row;
                    $final_round_match->looser_row=$final_round_match->looser_row;
                }
                $tournament->final_completed_round=$final_completed_round; 
            }
            $tournament->completed_rounds = TournamentRounds::where('tournament_id', $tournament->id)
                ->where('status', 'completed')
                ->count();
            if($tournament->completed_rounds < 1){
                $tournament->rounds = $tournament->rounds;
                foreach($tournament->rounds as $round){
                    $round->matches=$round->matches;
                    foreach($round->matches as $match){
                        $match->team_1=$match->team_1;
                        $match->team_2=$match->team_2;
                        $match->winner_row=$match->winner_row;
                        $match->looser_row=$match->looser_row;
                    }
                }
                 $latestCompletedRound = TournamentRounds::where('tournament_id', $tournament->id)
                    ->where('status', 'completed')
                    ->latest()
                    ->first();
                
                 $pendingMatchesArr=[];
                if($latestCompletedRound){
                   
                    $completedMatches=TournamentMatches::where(['round_id'=>$latestCompletedRound->id,'status'=>1])->get();
                    if(count($completedMatches) > 0){
                        foreach($completedMatches as $completedMatche){
                            $pendingMatchesArr[]=$completedMatche->winner;
                        }
                    }
                }
                if(!empty($tournament->available_teams)){
                        if (strpos($tournament->available_teams, ',') !== false) {
                            $availableTeamsArr = explode(',', $tournament->available_teams);
                            $pendingMatchesArr=array_merge($pendingMatchesArr, $availableTeamsArr);
                        } else {
                            $pendingMatchesArr[]=$tournament->available_teams;
                        }
                }
                $tournament->pending_teams=$pendingMatchesArr;
                $tournament->latestCompletedRound=$latestCompletedRound;
            }
            else if($tournament->completed_rounds>= 1){
                $winners_arr=array();
                $loosers_arr=array();
                $loosers_matches_arr=array();
                $pendingLoosersMatchesArr=[];
                $tournament->rounds = $tournament->rounds;

                $looser_pool_arr=array();
                if($this->isCommaSeparated($tournament->looser_pool)){
                    $looser_pool_arr=explode(",",$tournament->looser_pool);
                }
                else{
                    $looser_pool_arr[]=$tournament->looser_pool;
                }

                foreach($tournament->rounds as $round){
                    $winObject=(Object)[];
                    if($round->team_type=='win'){
                        
                        $winObject->id=$round->id;
                        $winObject->round_no=$round->round_no;
                        $winObject->status=$round->status; 
                        $winObject->matches=$round->matches;
                        $winObject->team_type=$round->team_type;
                        foreach($winObject->matches as $winMatch){
                            $winMatch->winner_row=$winMatch->winner_row;
                        }
                        $winners_arr[]=$winObject;
                    }
                    $loseObject=(Object)[];
                    if($round->team_type=='lose'){
                        
                        $loseObject->id=$round->id;
                        $loseObject->round_no=$round->round_no;
                        $loseObject->status=$round->status;
                        $loseObject->team_type=$round->team_type;
                        $loseObject->matches=$round->matches;

                        foreach($loseObject->matches as $looseMatch){
                            $looseMatch->looser_row=$looseMatch->looser_row;
                        }
                        $loosers_arr[]=$loseObject;
                    }

                    $round->matches=$round->matches;
                    
                    
                    foreach($round->matches as $match){
                        $match->team_1=$match->team_1;
                        $match->team_2=$match->team_2;
                        $match->winner_row=$match->winner_row;
                        $match->looser_row=$match->looser_row;
                        if(!empty($match->looser) && $match->looser > 0){
                            $pendingLoosersMatchesArr[]=$match->looser;
                            if(in_array($match->looser, $looser_pool_arr)){
                                $loosers_matches_arr[]=$match->looser_row;
                            }
                            
                        }
                        
                    }
                    
                    
                    

                }
                $latestLooserInProgressRound=(Object)[];
                if($latestLooserInProgressRound = TournamentRounds::where('tournament_id', $tournament->id)
                    ->where('status', 'in_progress')->where('team_type','lose')
                    ->latest()
                    ->first()){
                    $latestLooserInProgressRound->matches=$latestLooserInProgressRound->matches;
                    foreach($latestLooserInProgressRound->matches as $loose_match){
                        $loose_match->team_1=$loose_match->team_1;
                        $loose_match->team_2=$loose_match->team_2;
                        $loose_match->winner_row=$loose_match->winner_row;
                        $loose_match->looser_row=$loose_match->looser_row;
                    }
                    //....
                    $idsToRemove = array_merge(
                        $latestLooserInProgressRound->matches->pluck('team1')->all(),
                        $latestLooserInProgressRound->matches->pluck('team2')->all()
                    );

                    // Remove objects from the first array based on matching IDs
                    $filteredArray = array_filter($loosers_matches_arr, function ($item) use ($idsToRemove) {
                        return !in_array($item->id, $idsToRemove);
                    });

                    // Convert the associative array to an indexed array
                    $filteredArray = array_values($filteredArray);
                    $loosers_matches_arr=$filteredArray;
                }
                $tournament->loose_in_progress_round=$latestLooserInProgressRound;

                $tournament->winners_arr=$winners_arr;
                $tournament->loosers_arr=$loosers_arr;
                $tournament->loosers_matches_arr=$loosers_matches_arr;


                $latestCompletedRound = TournamentRounds::where('tournament_id', $tournament->id)
                    ->where('status', 'completed')
                    ->latest()
                    ->first();
                
                 $pendingMatchesArr=[];
                if($latestCompletedRound){
                   
                    $completedMatches=TournamentMatches::where(['round_id'=>$latestCompletedRound->id,'status'=>1])->get();
                    if(count($completedMatches) > 0){
                        foreach($completedMatches as $completedMatche){
                            $pendingMatchesArr[]=$completedMatche->winner;
                        }
                    }
                }
                if(!empty($tournament->available_teams)){
                        if (strpos($tournament->available_teams, ',') !== false) {
                            $availableTeamsArr = explode(',', $tournament->available_teams);
                            $pendingMatchesArr=array_merge($pendingMatchesArr, $availableTeamsArr);
                        } else {
                            $pendingMatchesArr[]=$tournament->available_teams;
                        }
                }
                $tournament->pending_winner_teams=$pendingMatchesArr;
                $tournament->pending_looser_teams=$pendingLoosersMatchesArr;
                $loosing_teams=0;
                if(!empty($tournament->looser_pool)){
                    if($this->isCommaSeparated($tournament->looser_pool)){
                        $loosing_teams=explode(",",$tournament->looser_pool);
                        $loosing_teams=count($loosing_teams);
                    }
                }
                if(!empty($tournament->eleminated_pool) && !empty($tournament->looser_pool) && !empty($tournament->winners_pool)){
                    $eleminated_pool_array=array();
                    $looser_pool_array=array();
                    $winners_pool_array=array();
                    if($this->isCommaSeparated($tournament->eleminated_pool)){
                        $eleminated_pool_array=explode(",",$tournament->eleminated_pool);
                    }
                    else{
                        $eleminated_pool_array[]=$tournament->eleminated_pool;
                    }
                    if($this->isCommaSeparated($tournament->looser_pool)){
                        $looser_pool_array=explode(",",$tournament->looser_pool);
                    }
                    else{
                        $looser_pool_array[]=$tournament->looser_pool;
                    }
                    if($this->isCommaSeparated($tournament->winners_pool)){
                        $winners_pool_array=explode(",",$tournament->winners_pool);
                    }
                    else{
                        $winners_pool_array[]=$tournament->winners_pool;
                    }
                    if(count($eleminated_pool_array) + 2==$teamsCount && count($winners_pool_array)==1 && count($looser_pool_array)==1){
                        $tournament->final_match_round=1;
                    }
                    
                    
                }
                
                $tournament->pending_looser_pool=$loosing_teams;
                $tournament->latestCompletedRound=$latestCompletedRound;
            }


            
        }
        return response()->json([
            'data' => $tournament,
            'teamsCount' => $teamsCount,
            'acceptedTeamsCount'=>Team::where('status', 'accepted')->where('tournament_id',$tournament->id)->count()
        ], 200);
    }
    public function tournamentRoundDetail(Request $request){
        
        $tournament = Tournament::with([
            'images', 
            'tournamentCategories',
            'category',
            'teams.teamMembers',
            'reviews.user',
            'tournamentType',
            ])
        ->where('is_active', 1)
        ->where('id', $request->id)
        ->first();

        $teamsCount = $tournament->teams->count();
        if($round=TournamentRounds::where(['tournament_id'=>$tournament->id,'id'=>$request->round_id])->get()->first()){
            $round->matches=$round->matches;
            foreach($round->matches as $match){
                $match->team_1=$match->team_1;
                $match->team_2=$match->team_2;
                $match->winner_row=$match->winner_row;
            }
            $tournament->round = $round;
        }

        
        // foreach($tournament->rounds as $round){
            
        // }
        return response()->json([
            'data' => $tournament,
            'teamsCount' => $teamsCount,
            'round_id' => $request->round_id,
            'acceptedTeamsCount'=>Team::where('status', 'accepted')->where('tournament_id',$tournament->id)->count()
        ], 200);
    }
    public function getDetails(){
        $categories = Category::all();
        $tournamentTypes = TournamentType::all();
        $eventTyeps = EventType::all();
        $countries = Country::all();
        $numberOfTeams = NumberOfTeam::all();
        $tournamentFormats = TournamentFormat::all();
        $tournamentLevels = TournamentLevel::all();
        return response()->json([
            'categories' => $categories,
            'tournamentTypes' => $tournamentTypes,
            'eventTyeps' => $eventTyeps,
            'countries' => $countries,
            'numberOfTeams' => $numberOfTeams,
            'tournamentFormats' => $tournamentFormats,
            'tournamentLevels' => $tournamentLevels,
            'tournament_fee' => !empty(DB::table('site_settings')->where('key', 'tournament_fee')->first()) ? DB::table('site_settings')->where('key', 'tournament_fee')->first()->value ? DB::table('site_settings')->where('key', 'tournament_fee')->first()->value : 0 : 0,
        ], 200);
    }

    public function getAll(Request $request){
        $sort = 'desc';
        if($request->has('sort')){
            $sort = $request->sort;
        }
        $tournaments = Tournament::with(['images', 'tournamentCategories','category'])
            ->where('is_active', 1)
            ->orderBy('id', $sort)
            ->latest()
            ->paginate(10);
        if($request->has('category') || $request->has('name')){
            if($request->category != ""){
                $tournaments = Tournament::with(['images', 'tournamentCategories','category'])->where('is_active', 1)
                ->whereHas('category', function($query) use ($request){
                    $query->where('name', $request->category);
                })
                ->orderBy('id', $sort)
                ->paginate(10);
            }
            if($request->name != ""){
                $tournaments = Tournament::with(['images', 'tournamentCategories','category'])->where('is_active', 1)
                ->where('title','like', '%'.$request->name.'%')
                ->orderBy('id', $sort)
                ->paginate(10);
            }
            if($request->category != "" && $request->name != ""){
                $tournaments = Tournament::with(['images', 'tournamentCategories','category'])->where('is_active', 1)
                ->whereHas('category', function($query) use ($request){
                    $query->where('name', $request->category);
                })
                ->orWhere('title','like', '%'.$request->name.'%')
                ->orderBy('id', $sort)
                ->paginate(10);
            }
        }
        return response()->json(['data' => $tournaments,'categories'=>Category::where('status','active')->orderBy('created_at', 'asc')->get()], 200);
    }
    public function getTournament($id){
        $tournament = Tournament::with(['tournamentImages', 'tournamentCategories'])->where('id', $id)->first();
        return response()->json(['data' => $tournament], 200);
    }
    // save tournament
    public function upload(Request $request){

        $data = $request->all();
        if($request->hasFile('files')){
            return response()->json(['abc' => $data], 200);
        }
    }
    // save tournament
    public function save_match_score(Request $request,$id){
        if(!empty($request->user_id) && $user = User::where('id', $request->user_id)->first()){
            if(!empty($request->tournament_id) && $tournament=Tournament::where('id', $request->tournament_id)->where('user_id', $request->user_id)->where('is_started',1)->get()->first()){
                if(!empty($id) && $match=TournamentMatches::where('id', $id)->get()->first()){
                   $validator = Validator::make($request->all(), [
                        'team1_score' => 'required',
                        'team2_score' => 'required',
                        'winner' => 'required',
                    ]);
                     if ($validator->fails()) {
                        return response()->json(['msg' => $validator->errors(),'status'=>0], 200);
                    }
                    else{
                        $match->team1_score=$request->team1_score;
                        $match->team2_score=$request->team2_score;
                        $match->winner=$request->winner;
                        $match->looser=intval($request->winner)==$match->team1 ? $match->team2 : $match->team1;
                        $match->status=1;
                        $match->update();
                        if(intval($request->final_round)==1){
                            $eleminated_pool_flag=$this->isCommaSeparated($tournament->eleminated_pool);
                            if($eleminated_pool_flag && $eleminated_pool_flag==true){
                                $eleminated_pool_arr = explode(',', $tournament->eleminated_pool);
                                $looser_item=intval($request->winner)==$match->team1 ? $match->team2 : $match->team1;
                                array_push($eleminated_pool_arr, $looser_item);
                                $tournament->eleminated_pool=implode(",",$eleminated_pool_arr);
                                $tournament->update();
                            }
                        }
                        else{
                            if(intval($request->loose_round)!=1){
                                //for winner side winners
                                if(!empty($tournament->winners_pool) && $tournament->winners_pool!=null && $tournament->winners_pool!='null'){
                                    $winners_pool_flag=$this->isCommaSeparated($tournament->winners_pool);
                                    if($winners_pool_flag && $winners_pool_flag==true){
                                        $winners_pool_arr = explode(',', $tournament->winners_pool);
                                        array_push($winners_pool_arr, $request->winner);
                                        $tournament->winners_pool=implode(",",$winners_pool_arr);
                                        $tournament->available_teams=implode(",",$winners_pool_arr);
                                        $tournament->update();
                                    }
                                    else{
                                        $winners_arr=array();
                                        $winners_arr[]=$tournament->winners_pool;
                                        $winners_arr[]=$request->winner;
                                        $tournament->winners_pool=implode(",",$winners_arr);
                                        $tournament->available_teams=implode(",",$winners_arr);
                                        $tournament->update();
                                    }
                                }
                                else{
                                    $tournament->available_teams=$request->winner;
                                    $tournament->winners_pool=$request->winner;
                                    $tournament->update();
                                }

                                //for winner side loosers
                                if(!empty($tournament->looser_pool) && $tournament->looser_pool!=null && $tournament->looser_pool!='null'){
                                    $looser_pool_flag=$this->isCommaSeparated($tournament->looser_pool);
                                    if($looser_pool_flag && $looser_pool_flag==true){
                                        $looser_pool_arr = explode(',', $tournament->looser_pool);
                                        $looser_item=intval($request->winner)==$match->team1 ? $match->team2 : $match->team1;
                                        array_push($looser_pool_arr, $looser_item);
                                        $tournament->looser_pool=implode(",",$looser_pool_arr);
                                        $tournament->update();
                                    }
                                    else{
                                        $looser_item=intval($request->winner)==$match->team1 ? $match->team2 : $match->team1;
                                        $loosers_arr=array();
                                        $loosers_arr[]=$tournament->looser_pool;
                                        $loosers_arr[]=$looser_item;
                                        $tournament->looser_pool=implode(",",$loosers_arr);
                                        $tournament->update();
                                    }
                                }
                                else{
                                    $looser_item=intval($request->winner)==$match->team1 ? $match->team2 : $match->team1;
                                    $tournament->looser_pool=$looser_item;
                                    $tournament->update();
                                } 
                            }
                            else if(intval($request->loose_round)==1){
                                //for loosers side loosers
                                $eleminated_pool_flag=$this->isCommaSeparated($tournament->eleminated_pool);
                                if($eleminated_pool_flag && $eleminated_pool_flag==true){
                                    $eleminated_pool_arr = explode(',', $tournament->eleminated_pool);
                                    $looser_item=intval($request->winner)==$match->team1 ? $match->team2 : $match->team1;
                                    array_push($eleminated_pool_arr, $looser_item);
                                    $tournament->eleminated_pool=implode(",",$eleminated_pool_arr);
                                    $tournament->update();
                                }
                                else{
                                    $looser_item=intval($request->winner)==$match->team1 ? $match->team2 : $match->team1;
                                    $eleminated_pool_arr=array();
                                    $eleminated_pool_arr[]=$looser_item;
                                    $tournament->eleminated_pool=implode(",",$eleminated_pool_arr);
                                    $tournament->update();
                                }
                                //for loosers side winning
                                if(!empty($tournament->looser_pool) && $tournament->looser_pool!=null && $tournament->looser_pool!='null'){
                                    $winners_pool_flag=$this->isCommaSeparated($tournament->looser_pool);
                                    if($winners_pool_flag && $winners_pool_flag==true){
                                        $winners_pool_arr = explode(',', $tournament->looser_pool);
                                        array_push($winners_pool_arr, $request->winner);
                                        $tournament->looser_pool=implode(",",$winners_pool_arr);
                                        $tournament->update();
                                    }
                                    else{
                                        $winners_arr=array();
                                        $winners_arr[]=$tournament->looser_pool;
                                        $winners_arr[]=$request->winner;
                                        $tournament->looser_pool=implode(",",$winners_arr);
                                        $tournament->update();
                                    }
                                }
                                else{
                                    $tournament->looser_pool=$request->winner;
                                    $tournament->update();
                                }
                            }  
                        }
                        
                        
                        $pendingMatches=TournamentMatches::where(['round_id'=>$match->round_id,'status'=>0])->count();
                        if($pendingMatches<=0){
                            TournamentRounds::where(['id'=>$match->round_id])->update(['status'=>'completed']);
                        }
                        return response()->json(['msg' => 'Score updated!','status'=>1], 200);
                    }
                }
                else{
                    return response()->json(['msg' => 'Invalid match!','status'=>0], 200);
                }
            }
            else{
                return response()->json(['msg' => 'Invalid tournament!','status'=>0], 200);
            }
        }
        else{
            return response()->json(['msg' => 'Invalid User!','status'=>0], 200);
        }
    }
    public function start_next_round(Request $request,$id){
        if(!empty($request->user_id) && $user = User::where('id', $request->user_id)->first()){
            if(!empty($id) && $tournament=Tournament::where('id', $id)->where('user_id', $request->user_id)->where('is_started',1)->get()->first()){
                $post = $request->all();
                if($tournament->match_type=='single'){
                        $latestCompletedRound = TournamentRounds::where('tournament_id', $tournament->id)
                            ->where('status', 'completed')
                            ->latest()
                            ->first();
                         $teamIds=[];
                        if($latestCompletedRound){
                           
                            $completedMatches=TournamentMatches::where(['round_id'=>$latestCompletedRound->id,'status'=>1])->get();
                            if(count($completedMatches) > 0){
                                foreach($completedMatches as $completedMatche){
                                    $teamIds[]=$completedMatche->winner;
                                }
                            }
                        }
                        if(!empty($tournament->available_teams)){
                                if (strpos($tournament->available_teams, ',') !== false) {
                                    $availableTeamsArr = explode(',', $tournament->available_teams);
                                    $teamIds=array_merge($teamIds, $availableTeamsArr);
                                } else {
                                    $teamIds[]=$tournament->available_teams;
                                }
                        }
                        $total_number_matches=$this->findClosestNumber(count($teamIds));

                        $tournamentRound=TournamentRounds::create(array(
                            'tournament_id'=>$tournament->id,
                            'user_id'=>$user->id,
                            'type'=>$latestCompletedRound->type,
                            'round_no'=>intval($latestCompletedRound->round_no) + 1
                        ));
                        //total teams
                        $total_teams=$total_number_matches * 2;

                        $remaining_teams=array();
                        $chosenNumbers = [];
                        $remainingNumbers = $teamIds;
                        $chosen_matches=array();
                        for($i=1;$i<=$total_number_matches;$i++){
                            $result1 = $this->chooseTwoRandomNumbers($remainingNumbers, $chosenNumbers);
                            // print_r($result1);die;
                            $chosenNumbers = array_merge($chosenNumbers, $result1['chosen']);
                            $chosen_teams=$result1['chosen'];
                            $remainingNumbers = $result1['remaining'];
                            TournamentMatches::create(array(
                                'round_id'=>$tournamentRound->id,
                                'team1'=>$chosen_teams[0],
                                'team2'=>$chosen_teams[1],
                            ));
                        }
                        $tournament->available_teams=count($remainingNumbers) > 0 ? implode(",", $remainingNumbers) : "";
                        $tournament->is_started=1;
                        $tournament->match_type=$latestCompletedRound->type;
                        $tournament->update();
                        return response()->json(['chosen_matches' => $chosen_matches,'remainingNumbers'=>$remainingNumbers,'status'=>1,'tournament_id'=>$tournament->id,'round_id'=>$tournamentRound->id], 200);
                }
                else if($tournament->match_type=='double'){
                    if($request->type=='win'){
                        $latestCompletedRound = TournamentRounds::where('tournament_id', $tournament->id)
                            ->where('status', 'completed')
                            ->latest()
                            ->first();
                         $teamIds=[];
                        // if($latestCompletedRound){
                           
                        //     $completedMatches=TournamentMatches::where(['round_id'=>$latestCompletedRound->id,'status'=>1])->get();
                        //     if(count($completedMatches) > 0){
                        //         foreach($completedMatches as $completedMatche){
                        //             $teamIds[]=$completedMatche->winner;
                        //         }
                        //     }
                        // }
                        // if(!empty($tournament->available_teams)){
                        //         if (strpos($tournament->available_teams, ',') !== false) {
                        //             $availableTeamsArr = explode(',', $tournament->available_teams);
                        //             $teamIds=array_merge($teamIds, $availableTeamsArr);
                        //         } else {
                        //             $teamIds[]=$tournament->available_teams;
                        //         }
                        // }
                        if($this->isCommaSeparated($tournament->winners_pool)){
                            $teamIds=explode(",",$tournament->winners_pool);
                        }
                        else{
                            $teamIds[]=$tournament->winners_pool;
                        }
                        $total_number_matches=$this->findClosestNumber(count($teamIds));

                        $tournamentRound=TournamentRounds::create(array(
                            'tournament_id'=>$tournament->id,
                            'user_id'=>$user->id,
                            'type'=>$latestCompletedRound->type,
                            'round_no'=>intval($latestCompletedRound->round_no) + 1
                        ));
                        //total teams
                        $total_teams=$total_number_matches * 2;

                        $remaining_teams=array();
                        $chosenNumbers = [];
                        $remainingNumbers = $teamIds;
                        $chosen_matches=array();
                        for($i=1;$i<=$total_number_matches;$i++){
                            $result1 = $this->chooseTwoRandomNumbers($remainingNumbers, $chosenNumbers);
                            // print_r($result1);die;
                            $chosenNumbers = array_merge($chosenNumbers, $result1['chosen']);
                            $chosen_teams=$result1['chosen'];
                            $remainingNumbers = $result1['remaining'];
                            TournamentMatches::create(array(
                                'round_id'=>$tournamentRound->id,
                                'team1'=>$chosen_teams[0],
                                'team2'=>$chosen_teams[1],
                            ));
                        }

                        $tournament->available_teams=count($remainingNumbers) > 0 ? implode(",", $remainingNumbers) : "";
                        $tournament->winners_pool=count($remainingNumbers) > 0 ? implode(",", $remainingNumbers) : "";
                        $tournament->is_started=1;
                        $tournament->match_type=$latestCompletedRound->type;
                        $tournament->update();
                        return response()->json(['chosen_matches' => $chosen_matches,'remainingNumbers'=>$remainingNumbers,'status'=>1,'tournament_id'=>$tournament->id,'round_id'=>$tournamentRound->id], 200);
                    }
                    else if($request->type=='lose'){
                        $latestCompletedRound = TournamentRounds::where('tournament_id', $tournament->id)
                            ->where('status', 'completed')
                            ->latest()
                            ->first();
                         $teamIds=[];
                         if($this->isCommaSeparated($tournament->looser_pool)){
                            $teamIds=explode(",",$tournament->looser_pool);
                        }
                        else{
                            $teamIds[]=$tournament->looser_pool;
                        }

                        $total_number_matches=$this->findClosestNumber(count($teamIds));




                         $tournamentRound=TournamentRounds::create(array(
                            'tournament_id'=>$tournament->id,
                            'user_id'=>$user->id,
                            'type'=>$latestCompletedRound->type,
                            'team_type'=>'lose',
                            'round_no'=>intval($latestCompletedRound->round_no) + 1
                        ));
                        //total teams
                        $total_teams=$total_number_matches * 2;

                        $remaining_teams=array();
                        $chosenNumbers = [];
                        $remainingNumbers = $teamIds;
                        $chosen_matches=array();
                        for($i=1;$i<=$total_number_matches;$i++){
                            $result1 = $this->chooseTwoRandomNumbers($remainingNumbers, $chosenNumbers);
                            // print_r($result1);die;
                            $chosenNumbers = array_merge($chosenNumbers, $result1['chosen']);
                            $chosen_teams=$result1['chosen'];
                            $remainingNumbers = $result1['remaining'];
                            TournamentMatches::create(array(
                                'round_id'=>$tournamentRound->id,
                                'team1'=>$chosen_teams[0],
                                'team2'=>$chosen_teams[1],
                            ));
                        }

                        $tournament->available_teams=count($remainingNumbers) > 0 ? implode(",", $remainingNumbers) : "";
                        $tournament->looser_pool=count($remainingNumbers) > 0 ? implode(",", $remainingNumbers) : "";
                        $tournament->is_started=1;
                        $tournament->match_type=$latestCompletedRound->type;
                        $tournament->update();
                        return response()->json(['chosen_matches' => $chosen_matches,'remainingNumbers'=>$remainingNumbers,'status'=>1,'tournament_id'=>$tournament->id,'round_id'=>$tournamentRound->id], 200);
                    }
                    else if($request->type=='final'){
                        $latestCompletedRound = TournamentRounds::where('tournament_id', $tournament->id)
                            ->where('status', 'completed')
                            ->latest()
                            ->first();
                        $teamsCount = $tournament->teams->count();
                        if(!empty($tournament->eleminated_pool) && !empty($tournament->looser_pool) && !empty($tournament->winners_pool)){
                            $eleminated_pool_array=array();
                            $looser_pool_array=array();
                            $winners_pool_array=array();
                            if($this->isCommaSeparated($tournament->eleminated_pool)){
                                $eleminated_pool_array=explode(",",$tournament->eleminated_pool);
                            }
                            else{
                                $eleminated_pool_array[]=$tournament->eleminated_pool;
                            }
                            if($this->isCommaSeparated($tournament->looser_pool)){
                                $looser_pool_array=explode(",",$tournament->looser_pool);
                            }
                            else{
                                $looser_pool_array[]=$tournament->looser_pool;
                            }
                            if($this->isCommaSeparated($tournament->winners_pool)){
                                $winners_pool_array=explode(",",$tournament->winners_pool);
                            }
                            else{
                                $winners_pool_array[]=$tournament->winners_pool;
                            }
                            if(count($eleminated_pool_array) + 2==$teamsCount && count($winners_pool_array)==1 && count($winners_pool_array)==1){
                                $tournamentRound=TournamentRounds::create(array(
                                    'tournament_id'=>$tournament->id,
                                    'user_id'=>$user->id,
                                    'type'=>'double',
                                    'team_type'=>'final',
                                    'round_no'=>intval($latestCompletedRound->round_no) + 1
                                ));
                                TournamentMatches::create(array(
                                    'round_id'=>$tournamentRound->id,
                                    'team1'=>$tournament->looser_pool,
                                    'team2'=>$tournament->winners_pool,
                                ));
                                $tournament->available_teams=null;
                                $tournament->winners_pool=null;
                                $tournament->looser_pool=null;
                                $tournament->is_started=1;
                                $tournament->update();
                                return response()->json(['status'=>1,'tournament_id'=>$tournament->id,'round_id'=>$tournamentRound->id], 200);
                            }
                            else{
                                return response()->json(['msg' => 'This is not final round!Technical problem here!','status'=>0], 200);
                            }
                            
                        }
                        else{
                            return response()->json(['msg' => 'This is not final round!Technical problem here!','status'=>0], 200);
                        }
                    }
                    else{
                        return response()->json(['msg' => 'Invalid double elemination request type!','status'=>0], 200);
                    }
                }
                else{
                    return response()->json(['msg' => 'Invalid request type!','status'=>0], 200);
                }


                    
            }
            else{
                return response()->json(['msg' => 'Invalid tournament!','status'=>0], 200);
            }
        }
        else{
            return response()->json(['msg' => 'Invalid User!','status'=>0], 200);
        }
    }
     public function start_tournament(Request $request,$id){
        if(!empty($request->user_id) && $user = User::where('id', $request->user_id)->first()){
            if(!empty($id) && $tournament=Tournament::where('id', $id)->where('user_id', $request->user_id)->where('is_started',0)->get()->first()){
                $post = $request->all();
                if(!empty($request->type)){

                    //total tournament teams
                    $tournamant_teams_count=Team::where('status', 'accepted')->where('tournament_id',$tournament->id)->count();
                    if($tournamant_teams_count > 0){
                        $teamIds = Team::where('tournament_id', $tournament->id)->where('status', 'accepted')->pluck('id')->toArray();
                        $total_number_matches=$this->findClosestNumber($tournamant_teams_count);
                        if($request->type=='single'){
                            $tournamentRound=TournamentRounds::create(array(
                                'tournament_id'=>$tournament->id,
                                'user_id'=>$user->id,
                                'type'=>$request->type,
                                'round_no'=>1
                            ));
                            //total teams
                            $total_teams=$total_number_matches * 2;
    
                            $remaining_teams=array();
                            $chosenNumbers = [];
                            $remainingNumbers = $teamIds;
                            $chosen_matches=array();
                            for($i=1;$i<=$total_number_matches;$i++){
                                $result1 = $this->chooseTwoRandomNumbers($remainingNumbers, $chosenNumbers);
                                // print_r($result1);die;
                                $chosenNumbers = array_merge($chosenNumbers, $result1['chosen']);
                                $chosen_teams=$result1['chosen'];
                                $remainingNumbers = $result1['remaining'];
                                TournamentMatches::create(array(
                                    'round_id'=>$tournamentRound->id,
                                    'team1'=>$chosen_teams[0],
                                    'team2'=>$chosen_teams[1],
                                ));
                            }
                            $tournament->available_teams=implode(",", $remainingNumbers);
                            $tournament->winners_pool=implode(",", $remainingNumbers);
                            $tournament->is_started=1;
                            $tournament->match_type=$request->type;
                            $tournament->update();
                            return response()->json(['chosen_matches' => $chosen_matches,'remainingNumbers'=>$remainingNumbers,'status'=>1,'tournament_id'=>$tournament->id,'round_id'=>$tournamentRound->id], 200);
                        }
                        else if($request->type=='double'){
                             $tournamentRound=TournamentRounds::create(array(
                                'tournament_id'=>$tournament->id,
                                'user_id'=>$user->id,
                                'type'=>$request->type,
                                'round_no'=>1
                            ));
                            //total teams
                            $total_teams=$total_number_matches * 2;
    
                            $remaining_teams=array();
                            $chosenNumbers = [];
                            $remainingNumbers = $teamIds;
                            $chosen_matches=array();
                            for($i=1;$i<=$total_number_matches;$i++){
                                $result1 = $this->chooseTwoRandomNumbers($remainingNumbers, $chosenNumbers);
                                // print_r($result1);die;
                                $chosenNumbers = array_merge($chosenNumbers, $result1['chosen']);
                                $chosen_teams=$result1['chosen'];
                                $remainingNumbers = $result1['remaining'];
                                TournamentMatches::create(array(
                                    'round_id'=>$tournamentRound->id,
                                    'team1'=>$chosen_teams[0],
                                    'team2'=>$chosen_teams[1],
                                ));
                            }
                            $tournament->available_teams=implode(",", $remainingNumbers);
                            $tournament->winners_pool=implode(",", $remainingNumbers);
                            $tournament->is_started=1;
                            $tournament->match_type=$request->type;
                            $tournament->update();
                            return response()->json(['chosen_matches' => $chosen_matches,'remainingNumbers'=>$remainingNumbers,'status'=>1,'tournament_id'=>$tournament->id,'round_id'=>$tournamentRound->id], 200);
                        }
                        else{
                             return response()->json(['msg' => 'Tournament type is invalid!','status'=>0], 200);
                        }
                        //success
                        
                    }
                    else{
                        return response()->json(['msg' => 'No teams are added to start the tournament!','status'=>0], 200);
                    }
                    
                    
                    
                }
                else{
                    return response()->json(['msg' => 'Type is required','status'=>0], 200);
                }
            }
            else{
                return response()->json(['msg' => 'Invalid tournament!','status'=>0], 200);
            }
        }
        else{
            return response()->json(['msg' => 'Invalid User!','status'=>0], 200);
        }
    }
    public function create(Request $request){
        
        $data = $request->all();
        $tournament = Tournament::create($data);
        if ($request->hasFile('logos')) {
            foreach($data['logos'] as $logo){
                $logo = $logo->store('uploads', 'public');
                $tournamentImage = new TournamentImage();
                $tournamentImage->tournament_id = $tournament->id;
                $tournamentImage->caption = 'logo';
                $tournamentImage->image = $logo;
                $tournamentImage->save();
            }
        }
         if ($request->hasFile('documents')) {
            foreach($data['documents'] as $document){
                $document = $document->store('uploads', 'public');
                $tournamentImage = new TournamentImage();
                $tournamentImage->tournament_id = $tournament->id;
                $tournamentImage->caption = 'document';
                $tournamentImage->image = $document;
                $tournamentImage->save();
            }
        }
        if ($request->hasFile('banners')) {
            foreach($data['banners'] as $logo){
                $banner = $logo->store('uploads', 'public');
                $tournamentImage = new TournamentImage();
                $tournamentImage->tournament_id = $tournament->id;
                $tournamentImage->caption = 'banner';
                $tournamentImage->image = $banner;
                $tournamentImage->save();
            }
        }
    
        if($tournament){
            return response()->json(
                [
                    'message' => 'Tournament saved successfully',
                    'tournament_id' => $tournament->id
                ], 
            200);
        }
        return response()->json(['message' => 'Something went wrong'], 400);
    }
    // update tournament
    public function update(Request $request, $id){
        $data = $request->all();
        $tournament = Tournament::where('id', $id)->update($data);
        // save tournament images
        if($request->hasFile('images')){
            foreach($request->file('images') as $image){
                $name = time().'_'.$image->getClientOriginalName();
                $image->move(public_path('uploads/tournament'), $name);
                $tournamentImage = new TournamentImage();
                $tournamentImage->tournament_id = $id;
                $tournamentImage->image = $name;
                $tournamentImage->save();
            }
        }
        // save tournament categories
        if($request->has('categories')){
            foreach($request->categories as $category){
                $tournamentCategory = new TournamentCategory();
                $tournamentCategory->tournament_id = $id;
                $tournamentCategory->category_id = $category;
                $tournamentCategory->save();
            }
        }
        return response()->json(['message' => 'Tournament updated successfully'], 200);
    }
    // delete tournament
    public function delete($id){
        $tournament = Tournament::where('id', $id)->delete();
        return response()->json(['message' => 'Tournament deleted successfully'], 200);
    }

    public function updatePaymentStatus($id){
        $tournament = Tournament::where('id', $id)->update(['payment_status' => 1]);
        return response()->json(['message' => 'Payment status updated successfully'], 200);
    }
    // create-indent-payment
    public function create_stripe_intent(Request $request){
        $res=array();
        $res['status']=0;
        // $header = $request->header('Authorization');
        // $member=$this->authenticate_verify_token($header);
        $input = $request->all();
        
        if($input){
            $stripe = new StripeClient('sk_test_51Moz1CFV8hMVqQzQZoplqqUTXaaIbqrJanKVG7hpwvHsH3x7uUl4euomLaicugVmjmXlga2ftQHvQ4UJNUHcDnNk00wom1iTYm');
            try{
                $amount = $input['amount'];
                if(!empty($input['expires_in'])){
                    // $expires_in=$input['expires_in'];
                    // $total=floatval($amount) * intval($expires_in);
                    $total=floatval($amount);
                }
                else{
                    $total=floatval($amount);
                }
                
                $cents = intval($total * 500);
                // if(!empty($member->customer_id)){
                //     $customer_id=$member->customer_id;
                // }
                // else{
                    $customer = $stripe->customers->create([
                        'email' =>'ammar@gmail.com',
                        'name' =>'Ammar Ali',
                        // 'address' => $stripe_adddress,
                    ]);
                    $customer_id=$customer->id;
                // }

                $intent= $stripe->paymentIntents->create([
                    'amount' => $cents,
                    'currency' => 'usd',
                    'customer'=>$customer_id,
                    // 'payment_method' => $vals['payment_method'],
                    'setup_future_usage' => 'off_session',
                ]);
                $setupintent=$stripe->setupIntents->create([
                    'customer' => $customer_id,
                ]);
                // return response()->json(['data' => $setupintent], 200);
                $arr=array(
                        'paymentIntentId'=>$intent->id,
                        'setup_client_secret'=>$setupintent->client_secret,
                        'setup_intent_id'=>$setupintent->id,
                        'client_secret'=>$intent->client_secret,
                        'customer'=>$customer_id,
                        'status'=>1
                );
                $res['arr']=$arr;
                $res['status']=1;
                return response()->json(['data' => $res], 200);
                    // print_r($res);
            }
            catch(Exception $e) {
                $arr['msg']="Error >> ".$e->getMessage();
                $arr['status']=0;
            }
        }
        exit(json_encode($res));
    }
    
}
