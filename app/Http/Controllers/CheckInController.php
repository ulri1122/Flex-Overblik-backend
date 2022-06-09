<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
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
            return $UserController->getStatus($user);
        }

        $respose = $CheckIn->checkIn($request);
        if (isset($respose['error'])) {
            return response(['error' => $respose['error']], 404);
        }
        return $user = $UserController->getUser($respose->user_id);

        // return array(
        //     'current_flex' => $UserController->getCurrentFlexBalance($user),
        //     'checkin_status' => $UserController->getStatus($user),
        // );
    }
    public function calculateFlex(Request $request)
    {

        $user = User::find($request['user_id']);
        $CheckIntimeRows = $this->getTimeStampsToCalculate($user->id);

        $lastCalculatedRow = $this->getLastCalculatedRow($CheckIntimeRows[0]->id, $user->id);
        if (!$lastCalculatedRow) {
            return 'missing time stamps';
        }
        $startDate = Carbon::create($lastCalculatedRow['checked_in'])->toIso8601String();
        $now = Carbon::now()->toIso8601String();
        $dates_to_calculate = $this->date_range($startDate, $now, '+1 day', "Y-m-d");


        $workDayController = new WorkDayController();
        //dette er et array som viser de timer man skal arbejde på en given dag.
        $workTimes = $workDayController->GetWorkTimes($user->id);
        $lastCalculatedFlex = $lastCalculatedRow['calculated_flex'];
        Log::alert($lastCalculatedFlex);
        Log::alert($dates_to_calculate);
        foreach ($dates_to_calculate as $key => $date_to_calculate) {
            $totalWorkForTheDay = 0;
            $workhours = $workDayController->getHoursToWorkOnDate($date_to_calculate, $workTimes);

            foreach ($CheckIntimeRows as $key1 => $CheckInTime) {

                $checkInTimeStamp = gmdate("Y-m-d", strtotime($CheckInTime['checked_in']));

                if ($checkInTimeStamp == $date_to_calculate) {
                    $totalWorkForTheDay += strtotime($CheckInTime['checked_out']) - strtotime($CheckInTime['checked_in']);
                }
            }

            $lastCalculatedFlex += $totalWorkForTheDay + (- ($workhours * 3600));
            Log::alert($lastCalculatedFlex);

            // CheckIn::find($timeStamp->id)->update(['calculated_flex' => null, 'calculated' => 0]);
        }
        return 'test';
    }



    public function getTimeStamps(int $user_id,)
    {
        return CheckIn::where('user_id', $user_id)->orderBy('id', 'desc')->get();
    }
    public function getTimeStampsToCalculate(int $user_id,)
    {

        $uncalculatedTimes = CheckIn::where('user_id', $user_id)->where('calculated', 0)->orderBy('id', 'asc')->first();
        if ($uncalculatedTimes) {
            return CheckIn::where('user_id', $user_id)->where('id', '>=', $uncalculatedTimes['id'])->get();
        } else {
            return [];
        }
    }
    public function getLastCalculatedrow($TimeStrampToCalculateFrom, $user_id)
    {
        $lastCalculatedTimeId = CheckIn::where('user_id', $user_id)->where('id', '<', $TimeStrampToCalculateFrom)->max('id');
        $lastCalculatedTimeRow = CheckIn::where('id', $lastCalculatedTimeId)->first();

        return $lastCalculatedTimeRow;
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
