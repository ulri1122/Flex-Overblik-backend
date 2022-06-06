<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    function createTeam(Request $request)
    {
        if (!isset($request['team_name'])) {
            return response(['error' => 'team does excist'], 404);
        }
        return Team::create([
            'team_name' => $request['team_name']
        ]);
        die("here");
    }
    // todo skal også finde users i team.
    public function getTeam(Request $request)
    {
        if (!isset($request['team_id'])) {
            return response(['error' => 'team does excist'], 404);
        }

        $team = new Team();
        $queried_user = $team->where('id', $request['team_id'])->first();
        return $queried_user;
    }
    public function getTeamsWidthUsers()
    {
        $team = new Team();
        $teams = $team->get();
        foreach ($teams as $key => $team) {
            $teams[$key]->users = $team->users()->get();

            foreach ($teams[$key]->users as $key1 => $user) {

                $UserController = new UserController();

                $user_obj = $UserController->getUser($user->id);
                $teams[$key]->users[$key1]['current_flex'] = $UserController->getCurrentFlexBalance($user_obj);
                $teams[$key]->users[$key1]['check_in_status'] = $UserController->getStatus($user_obj);
            }
        }
        return $teams;
    }
    public function getTeams()
    {
        $team = new Team();
        return $teams = $team->get();
    }
    public function deleteTeam(Request $request)
    {
        if (!$request['team_id']) {
            return response(['error' => 'missing_team_id'], 404);
        }
        Team::where('id', $request['team_id'])->delete();
        return $request['team_id'];
    }
    public function updateTeamName(Request $request)
    {
        if (!$request['team_id']) {
            return response(['error' => 'missing_team_id'], 404);
        }
        if (!$request['team_name']) {
            return response(['error' => 'missing_new_team_name'], 404);
        }
        Team::where('id', $request['team_id'])->update(['team_name' => $request['team_name']]);
        $data = Team::where('id', $request['team_id'])->first();

        return $data;
    }
}
