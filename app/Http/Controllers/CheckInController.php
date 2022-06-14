<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\OffDay;
use App\Models\User;
use App\Services\CheckInService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckInController extends Controller
{
    public function checkIn(Request $request)
    {
        $CheckIn = new CheckInService();
        $UserController = new UserController();
        if (isset($request['user_id'])) {
            $CheckIn->checkInWithUserId($request);
            $user = $UserController->getUser($request->user_id);
            return array(
                'current_flex' => $this->calculateFlex($user),
                'checkin_status' => $UserController->getStatus($user),
            );
        }

        $respose = $CheckIn->checkIn($request);
        if (isset($respose['error'])) {
            return response(['error' => $respose['error']], 404);
        }
        $user = $UserController->getUser($respose->user_id);

        return array(
            'current_flex' => $this->calculateFlex($user),
            'checkin_status' => $UserController->getStatus($user),
            'user_img' => $user->image_path,
            'username' => $user->name
        );
    }
    public function calculateFlex(User $user)
    {

        $CheckIntimeRows = $this->getTimeStampsToCalculate($user->id);

        if (!isset($CheckIntimeRows['id'])) {
            $lastCheckInRow = CheckIn::where('user_id', $user->id)->orderBy('id', 'desc')->first();
            return $lastCheckInRow['calculated_flex'] ?? 0;
        }

        $CheckIntimeRows = $this->getLastDayNotCalculated($CheckIntimeRows->checked_in, $user->id);

        if (!$CheckIntimeRows[0]) {
            return 'missing time stamps';
        }
        // Log::alert('first id to calculate');
        // Log::alert($CheckIntimeRows[0]['id']);
        $lastCalculatedTime = $this->getLastCalculatedTime($CheckIntimeRows[0]->id, $user->id);

        $startDate = gmDate("Y-m-d", strtotime($CheckIntimeRows[0]['checked_in']));
        $now = gmDate("Y-m-d", strtotime(Carbon::now()));
        $dates_to_calculate = $this->date_range($startDate, $now, '+1 day', "Y-m-d");


        $workDayController = new WorkDayController();
        //dette er et array som viser de timer man skal arbejde på en given dag.
        $workTimes = $workDayController->GetWorkTimes($user->id);

        if ($CheckIntimeRows[0]['calculated'] == 1) {
        }
        $CalculatedFlex = $lastCalculatedTime;

        foreach ($dates_to_calculate as $key => $date_to_calculate) {

            $totalTimeToWorkOnDay = -$workDayController->getTimeToWorkOnDate($date_to_calculate, $workTimes, $user->id);

            $CalculatedFlex = $CalculatedFlex + $totalTimeToWorkOnDay;


            foreach ($CheckIntimeRows as $key1 => $CheckInTime) {

                $checkInTimeStamp = gmdate("Y-m-d", strtotime($CheckInTime['checked_in']));


                if ($checkInTimeStamp == $date_to_calculate) {

                    if (is_null($CheckInTime['checked_out'])) {

                        $flexBetweenTs = strtotime(Carbon::now()) - strtotime($CheckInTime['checked_in']);
                        CheckIn::find($CheckInTime->id)->update(['calculated_flex' => $CalculatedFlex + $flexBetweenTs, 'calculated' => 0]);
                        $CalculatedFlex = $CalculatedFlex + $flexBetweenTs;
                        continue;
                    }

                    $flexBetweenTs = strtotime($CheckInTime['checked_out']) - strtotime($CheckInTime['checked_in']);
                    $CalculatedFlex = $CalculatedFlex + $flexBetweenTs;
                    $checkIn = CheckIn::find($CheckInTime->id)->update(['calculated_flex' => $CalculatedFlex, 'calculated' => 1]);
                }
            }
        }


        OffDay::where('user_id', $user->id)->update(['calculated' => 1]);


        return $CalculatedFlex;
    }



    public function getTimeStamps(int $user_id,)
    {
        return CheckIn::where('user_id', $user_id)->orderBy('id', 'desc')->get();
    }
    public function getTimeStampsToCalculate(int $user_id,)
    {
        $nonCalculatedOffDay = OffDay::Where('user_id', $user_id)->where('calculated', 0)->orWhere('calculated', null)->get();


        if (isset($nonCalculatedOffDay[0])) {
            $uncalculatedTimes = CheckIn::where('user_id', $user_id)->where('checked_in', '<=', gmdate('Y-m-d', strtotime($nonCalculatedOffDay->min('start_date'))))->orderBy('id', 'asc')->first();

            if (!$uncalculatedTimes) {
                return $ifNoPrev = CheckIn::where('user_id', $user_id)->orderBy('id', 'asc')->first();
            }
            return $uncalculatedTimes;
        }

        $uncalculatedTimes = CheckIn::where('user_id', $user_id)->where('calculated', 0)->orWhere('calculated', null)->orderBy('id', 'asc')->first();
        if ($uncalculatedTimes) {
            return $uncalculatedTimes;
        } else {
            return [];
        }
    }
    public function getLastDayNotCalculated($lastcheckInDate, $user_id)
    {
        $data = CheckIn::where('user_id', $user_id)->whereDate('checked_in', '>=', gmDate("Y-m-d", strtotime($lastcheckInDate)))->get();

        return $data;
    }
    public function getLastCalculatedTime($checkinId, $user_id)
    {

        $lastCalculatedTimeId = CheckIn::where('user_id', $user_id)->where('calculated', 1)->where('id', '<', $checkinId)->max('id');
        $lastCalculatedTimeRow = CheckIn::where('id', $lastCalculatedTimeId)->first();
        return $lastCalculatedTimeRow->calculated_flex ?? 0;
    }

    public function updateTimeStamp(Request $request)
    {
        //manger at tage højde for roll over date
        return CheckIn::find($request['checkin_id'])->update([
            'checked_in' => Carbon::parse($request['from_time'])->setTimezone('UTC'),
            'checked_out' => Carbon::parse($request['to_time'])->setTimezone('UTC'),
            'calculated' => 0
        ]);
    }
    public function deleteTimeStamp(Request $request)
    {
        $checkinobj = CheckIn::find($request['checkin_id']);
        $lastCalculatedTimeId = CheckIn::where('user_id', $checkinobj->user_id)->where('calculated', 1)->where('id', '<', $checkinobj->id)->max('id');
        $lastCalculatedTimeRow = CheckIn::where('id', $lastCalculatedTimeId)->first();
        if ($lastCalculatedTimeRow) {
            $lastCalculatedTimeRow->update(['calculated' => 0]);
        }
        $checkinobj->delete();
        return true;
    }

    public function addFlex(Request $request)
    {
        $date = Carbon::now();
        return CheckIn::create([
            'user_id' => $request['user_id'],
            'check_in_type' => 3,
            'calculated' => 0,
            'checked_in' => $date->toDateString(),
            'checked_out' => gmDate('Y-m-d H:i:s', (strtotime($date->toDateString()) + ($request['flex_amount'] ?? 0) * 3600))
        ]);
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
}
