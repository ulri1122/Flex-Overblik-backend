<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\NfcCards;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkTimes;
use App\Services\CheckInService;
use App\Services\NfcCardService;
use App\Services\UserService;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function getUser($user_id = 0)
    {
        $user = new User();
        $queried_user = $user->where('id', $user_id)->first();
        return $queried_user;
    }
    public function getUserOnNFCId($nfc_id)
    {
        if ($nfc_id['nfc_id'] == 0) {
            return response(['error' => 'missing_nfc_id'], 404);
        }


        $user = new User();
        $queried_user = $user->where('id', $nfc_id)->first();
        if ($queried_user) {
            return $queried_user;
        }
        return response(['error' => 'user_unknown'], 404);
    }
    public function createUser(Request $request)
    {
        if (!isset($request['username']))
            return response(['error' => 'missing_username'], 404);
        if (!isset($request['phone']))
            return response(['error' => 'missing_phone'], 404);
        if (!isset($request['email']))
            return response(['error' => 'missing_email'], 404);
        if (!isset($request['card_id']))
            return response(['error' => 'missing_card_id'], 404);
        if (!isset($request['team_id']))
            return response(['error' => 'missing_team_id'], 404);

        $team = Team::find($request['team_id']);
        if (!$team) {
            return response(['error' => 'team_not_found'], 404);
        }

        $UserService = new UserService();
        $NfcCards = new NfcCardService();


        if (isset($request['user_id']) && $request['user_id'] != 0) {
            $user = $this->getUser($request['user_id']);
            if (!$user) {
                return response(['error' => 'not found'], 404);
            }


            $UserService->updateUser($user, $request);

            $nfccard = $NfcCards->getNfcCard($request['card_id']);
            if (!$nfccard) {
                $NfcCards->createCard($user, $request['card_id']);
            }


            $current_user_teams = User::find($user->id)->teams()->where('team_id', $request['team_id'])->get();

            if (!isset($current_user_teams[0])) {
                User::find($user->id)->teams()->attach($team);
            }
            $user['teams'] = User::find($request['user_id'])->teams()->get() ?? [];

            return $user;
        }

        $user = $UserService->createUser($request);


        if (!$user) {
            return response(['error' => 'user_does_not_excist'], 404);
        }

        $NfcCards->createCard($user, $request['card_id']);

        User::find($user->id)->teams()->attach($team);

        return $user;
    }
    public function getCurrentFlexBalance(User $user)
    {
        $CheckInService = new CheckInService();
        $timeStamps = $CheckInService->getTimeStamps($user->id);
        $total_time = 0;
        $timeStamps[0] = isset($timeStamps[0]) ? $timeStamps[0] : array('created_at' => gmdate("c"));

        $total_work_hours_needed = $this->TotalWorkHoursNeeded($timeStamps[0]['created_at']);

        foreach ($timeStamps as $key => $timeStamp) {
            if (count($timeStamps) == 1) {
                $date_a = new DateTime($timeStamp['created_at']);
                $date_b = new DateTime(gmdate("Y-m-d H:i:s"));
                $diff = $date_b->getTimestamp() - $date_a->getTimestamp();
                $total_time += $diff;
                continue;
            } elseif ($key % 2 == 1) {
                continue;
            }

            if (!isset($timeStamps[$key + 1])) {
                $end_time = gmdate("Y-m-d H:i:s");
            } else {
                $end_time = $timeStamps[$key + 1]['created_at'];
            }
            $date_a = new DateTime($timeStamp['created_at']);
            $date_b = new DateTime($end_time);

            $diff = $date_b->getTimestamp() - $date_a->getTimestamp();
            $total_time += $diff;
        }
        if (true) {
            // man skal kunne tilføje flex her
        }
        $time =  $total_time - $total_work_hours_needed;

        return $time;
    }
    public function TotalWorkHoursNeeded($startDate)
    {

        $workhourdays = $this->getWorkHours();

        $dateRange = $this->date_range($startDate, date("c"), '+1 day', "c");
        $time_needed_for_work = 0;
        foreach ($dateRange as $key => $date) {
            $day_of_week = gmdate('N', strtotime($date));
            if ($day_of_week == '1') {
                $time_needed_for_work += $workhourdays['1'];
            }
            if ($day_of_week == '2') {
                $time_needed_for_work += $workhourdays['2'];
            }
            if ($day_of_week == '3') {
                $time_needed_for_work += $workhourdays['3'];
            }
            if ($day_of_week == '4') {
                $time_needed_for_work += $workhourdays['4'];
            }
            if ($day_of_week == '5') {
                $time_needed_for_work += $workhourdays['5'];
            }
            if ($day_of_week == '6') {
                $time_needed_for_work += $workhourdays['6'];
            }
            if ($day_of_week == '7') {
                $time_needed_for_work += $workhourdays['7'];
            }
        }
        return $time_needed_for_work * 3600;
    }
    private function date_range($first, $last, $step = '+1 day', $output_format = 'd/m/Y')
    {

        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);


        while ($current <= $last) {

            $dates[] = gmdate($output_format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }
    function getStatus($user)
    {
        if (!$user) {
            die("errro");
        }
        $checkInTypeRow = CheckIn::where('user_id', $user->id)->latest()->first();
        switch ($checkInTypeRow->check_in_type ?? false) {
            case 1:
                return "at_work";
            case 2:
                return "Working_home";
            default:
                return 'not_checked_in';
        }
    }
    function getUserProfile(Request $request)
    {
        if (!isset($request['id'])) {
            return response(['error' => 'missing_id'], 404);
        }

        $user = $this->getUser($request['id']);

        $user->current_flex = $this->getCurrentFlexBalance($user);
        $user->checkInDays = $this->getUserCheckInTimes($user);
        $user->check_in_state = $this->getStatus($user);
        return $user;
    }
    public function getUserCheckInTimes(user $user)
    {
        $CheckInService = new CheckInService();
        // skal tage de sidste 14 dage og ikke de sidste 14 records
        $time_stamps = $CheckInService->getTimeStamps($user->id, 40);
        if (!isset($time_stamps[0]['created_at'])) {
            return null;
        }

        $date_range = $this->date_range($time_stamps[count($time_stamps) - 1]['created_at'], $time_stamps[0]['created_at'],  '+1 day', 'c');


        $workHours = $this->getWorkhours();

        $resultsDateArray = array();

        foreach ($date_range as $key => $date) {

            $resultsDateArray[$key] = array('date' => $date);
            $resultsDateArray[$key]['time_at_work'] = 0;
            $resultsDateArray[$key]['time_to_work'] =  $workHours[gmdate('N', strtotime($date))] * 3600;
            $resultsDateArray[$key]['flex_balance_on_day'] = 0;
            $resultsDateArray[$key]['clock_in'] =  0;
            $resultsDateArray[$key]['clock_out'] =  0;


            $diff = 0;
            foreach ($time_stamps as $key1 => $time_stamp) {
                if (gmdate('Y/m/d', strtotime($time_stamp['created_at'])) ==  gmdate('Y/m/d', strtotime($date))) {
                    if ($key1 % 2 == 1) {
                        continue;
                    }

                    $resultsDateArray[$key]['times'][$key1]['from'] = $time_stamp['created_at'];
                    $from_time = strtotime($time_stamp['created_at']);


                    if (isset($time_stamps[$key1 + 1]['created_at'])) {
                        $resultsDateArray[$key]['times'][$key1]['to'] = $time_stamps[$key1 + 1]['created_at'];
                        $to_time = strtotime($time_stamps[$key1 + 1]['created_at']);
                    } else {
                        $to_time = strtotime(gmdate('c', time()));
                    }
                    $diff += $resultsDateArray[$key]['times'][$key1]['total_time'] = $to_time - $from_time;
                }
            }
            if (isset($resultsDateArray[$key]['times'])) {
                $resultsDateArray[$key]['times'] = array_values($resultsDateArray[$key]['times']);
            }
            if (isset($resultsDateArray[$key]['times'][0]['from'])) {
                $resultsDateArray[$key]['clock_in'] = $resultsDateArray[$key]['times'][0]['from'];
            }
            if (isset($resultsDateArray[$key]['times']) && isset($resultsDateArray[$key]['times'][count($resultsDateArray[$key]['times']) - 1]['to'])) {
                $resultsDateArray[$key]['clock_out'] = $resultsDateArray[$key]['times'][count($resultsDateArray[$key]['times']) - 1]['to'];
            }

            $resultsDateArray[$key]['flex_balance_on_day']  = $diff - $workHours[gmdate('N', strtotime($date))] * 3600;
            $resultsDateArray[$key]['time_at_work'] = $diff;
        }
        return $resultsDateArray;
    }
    public function getWorkHours()
    {
        return array(
            '1' => 8,
            '2' => 8,
            '3' => 8,
            '4' => 8,
            '5' => 6,
            '6' => 0,
            '7' => 0
        );
    }
    public function createUserToken(Request $request)
    {
        $user = $this->getUser($request['user_id']);
        $token = $user->createToken($request->header('User-Agent'));
        return ['token' => $token->plainTextToken, 'user-agent' => $request->header('User-Agent')];
    }
    public function revokeUserToken(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return "token revoked";
    }
    public function getUserForUpdate(Request $request)
    {
        $returnArray = [];

        $returnArray['user'] = $this->getUser($request['user_id']);
        $returnArray['teams'] = Team::get();
        $returnArray['card_data'] = NfcCards::where('user_id', $request['user_id'])->orderBy('id', 'desc')->first();
        if ($request['user_id'] != 0) {
            $returnArray['user']['teams'] = User::find($request['user_id'])->teams()->get() ?? [];
        }
        if (!isset($request['user_id'])) {
            $returnArray['work_times'] = WorkTimes::where('user_id', $request['user_id'])->first();
        }
        return $returnArray;
    }
    public function removeUserFromTeam(Request $request)
    {
        if (!isset($request['user_id'])) {
            return response(['error' => 'missing_user_id'], 404);
        }
        if (!isset($request['team_id'])) {
            return response(['error' => 'missing_team_id'], 404);
        }

        User::find($request['user_id'])->teams()->detach($request['team_id']);
        return ['success' => 'user_removed_from_team'];
    }
}
