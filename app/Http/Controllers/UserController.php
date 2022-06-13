<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\NfcCards;
use App\Models\OffDay;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkTimes;
use App\Services\CheckInService;
use App\Services\NfcCardService;
use App\Services\UserService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

use function Psy\debug;

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
        $WorkDayController = new WorkDayController();


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

            $user['workTimes'] = $WorkDayController->insertWorkTimes($request['weekdays'], $request['user_id']);



            return $user;
        }

        $user = $UserService->createUser($request);


        if (!$user) {
            return response(['error' => 'user_does_not_excist'], 404);
        }

        $NfcCards->createCard($user, $request['card_id']);

        User::find($user->id)->teams()->attach($team);

        $user['workTimes'] = $WorkDayController->insertWorkTimes($request['weekdays'], $user->id);
        return $user;
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

        if (is_null($checkInTypeRow->checked_out ?? null)) {
            switch ($checkInTypeRow->check_in_type ?? false) {
                case 1:
                    return "at_work";
                case 2:
                    return 'Working_home';
                default:
                    return 'not_checked_in';
            }
        }
        return $this->findStatus($user->id);
    }
    public function findStatus($user_id)
    {

        $offdays = OffDay::where('user_id', $user_id)->whereNull('deleted')->whereDate('start_date', '<=', Carbon::now())
            ->whereDate('end_date', '>=', Carbon::now())->latest()->first();
        if (!$offdays) {
            return "Working_home";
        }

        switch ($offdays->off_day_type ?? false) {
            case 0:
                return "on_leave";
            case 1:
                return 'sick';
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
        $CheckInController = new CheckInController();
        $user->current_flex = $CheckInController->calculateFlex($user);
        $user->checkInDays = $this->getUserCheckInTimes($user);
        $user->check_in_state = $this->getStatus($user);
        return $user;
    }

    //skal omskrives
    public function getUserCheckInTimes(user $user)
    {
        $CheckInService = new CheckInService();
        // skal tage de sidste 14 dage og ikke de sidste 14 records
        $time_stamps = $CheckInService->getTimeStamps($user->id, 40);

        if (!isset($time_stamps[0]['checked_in'])) {
            return null;
        }
        $startDate = gmDate("Y-m-d", strtotime($time_stamps[count($time_stamps) - 1]['checked_in']));
        $endDate = gmDate("Y-m-d", strtotime($time_stamps[0]['checked_in']));

        $date_range = $this->date_range($startDate, $endDate,  '+1 day', "Y-m-d");
        Log::alert($date_range);
        $workDayController = new WorkDayController();

        $workTimes = $workDayController->GetWorkTimes($user->id);

        $resultsDateArray = array();

        foreach ($date_range as $key => $date) {

            $resultsDateArray[$key] = array('date' => $date);
            $resultsDateArray[$key]['time_at_work'] = 0;
            $resultsDateArray[$key]['time_to_work'] =  $workDayController->getTimeToWorkOnDate($date, $workTimes, $user->id);
            $resultsDateArray[$key]['flex_balance_on_day'] = 0;
            $resultsDateArray[$key]['clock_in'] =  0;
            $resultsDateArray[$key]['clock_out'] =  0;


            $diff = 0;

            foreach ($time_stamps as $key1 => $time_stamp) {
                if (gmdate('Y/m/d', strtotime($time_stamp['checked_in'])) ==  gmdate('Y/m/d', strtotime($date))) {
                    $resultsDateArray[$key]['times'][$key1]['id'] = $time_stamp['id'];
                    $resultsDateArray[$key]['times'][$key1]['check_in_type'] = $time_stamp['check_in_type'];

                    $resultsDateArray[$key]['times'][$key1]['from'] = $time_stamp['checked_in'];
                    $from_time = strtotime($time_stamp['checked_in']);


                    if (isset($time_stamps[$key1]['checked_out'])) {
                        $resultsDateArray[$key]['times'][$key1]['to'] = $time_stamps[$key1]['checked_out'];
                        $to_time = strtotime($time_stamps[$key1]['checked_out']);
                    } else {
                        $to_time = strtotime(gmdate('c', time()));
                    }
                    $diff += $resultsDateArray[$key]['times'][$key1]['total_time'] =   $to_time - $from_time;
                }
            }
            if (isset($resultsDateArray[$key]['times'])) {
                $resultsDateArray[$key]['times'] = array_values($resultsDateArray[$key]['times']);
                $resultsDateArray[$key]['clock_in'] = $resultsDateArray[$key]['times'][count($resultsDateArray[$key]['times']) - 1]['from'];
                $resultsDateArray[$key]['clock_out'] = $resultsDateArray[$key]['times'][0]['to'] ?? '';
            }

            Log::alert($resultsDateArray[$key]['time_to_work']);
            $resultsDateArray[$key]['flex_balance_on_day']  = $diff - $resultsDateArray[$key]['time_to_work'];
            $resultsDateArray[$key]['time_at_work'] = $diff;
        }
        return $resultsDateArray;
    }
    public function getWorkHours($user_id)
    {
        $workDayController = new WorkDayController();

        return $workDayController->GetWorkTimesRevers($user_id)[0]['work_times_array'] ?? false;
    }

    public function createUserToken(Request $request)
    {
        $user = $this->getUser($request['user_id']);
        if ($user->is_admin) {

            $token = $user->createToken($request->header('User-Agent'));
            return ['token' => $token->plainTextToken, 'user-agent' => $request->header('User-Agent')];
        }
        return response(['error' => 'user_does_not_allowed'], 401);
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
        $returnArray['work_times'] = $this->getWorkHours($request['user_id']);
        if ($request['user_id'] != 0) {
            $returnArray['user']['teams'] = User::find($request['user_id'])->teams()->get() ?? [];
            $returnArray['days_off'] = OffDay::where('user_id', $request['user_id'])->whereNull('deleted')->get();
        }
        if (!isset($request['user_id']) && $request['user_id'] != 0) {
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
    public function deleteUser(Request $request)
    {
        NfcCards::where('user_id', $request['user_id'])->delete();
        $user = User::find($request['user_id']);
        $user->delete();
        return 'success';
    }
    public function AddOffDay(Request $request)
    {

        OffDay::create([

            'user_id' => $request['user_id'],
            'start_date' => $request['startDate'],
            'end_date' => $request['endDate'],
            'comment' => $request['comment'],
            'off_day_type' => $request['offType'],
        ]);
        return OffDay::where('user_id', $request['user_id'])->whereNull('deleted')->get();
    }
    public function deleteDayOff(request $request)
    {
        return OffDay::find($request['day_off_id'])->update(['deleted' => Carbon::now(), 'calculated' => 0]);
    }
    public function editDayOff(request $request)
    {


        return OffDay::find($request['day_off_id'])->update([
            'start_date' => $request['startDate'],
            'end_date' => $request['endDate'],
            'comment' => $request['comment'],
            'off_day_type' => $request['offType'],
            'calculated' => 0,
        ]);
    }
}
