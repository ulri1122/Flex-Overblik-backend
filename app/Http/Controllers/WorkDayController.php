<?php

namespace App\Http\Controllers;

use App\Models\OffDay;
use App\Models\WorkTimes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WorkDayController extends Controller
{
    public function InsertWorkTimes($workDays, $user_id)
    {
        WorkTimes::create([
            'user_id' => $user_id,
            'work_times_array' => json_decode($workDays)
        ]);
    }
    public function GetWorkTimes($user_id)
    {
        return WorkTimes::where('user_id', $user_id)->get();
    }
    public function GetWorkTimesRevers($user_id)
    {
        return WorkTimes::where('user_id', $user_id)->orderBy('id', 'desc')->get();
    }
    public function getTimeToWorkOnDate($date, $work_time_objs, $user_id)
    {

        $nonCalculatedOffDays = OffDay::Where('user_id', $user_id)->whereNull('deleted')->get();


        foreach ($work_time_objs as $key => $work_time_obj) {
            $datefrom = Carbon::parse($work_time_obj['created_at']);
            if (isset($work_time_objs[$key + 1])) {
                $dateTo = Carbon::parse($work_time_objs[$key + 1]['created_at']);
            } else {
                $dateTo = Carbon::parse(Carbon::now());
            }

            foreach ($nonCalculatedOffDays as $key => $nonCalculatedOffDay) {
                if (Carbon::parse($date)->between(Carbon::parse($nonCalculatedOffDay['start_date']), Carbon::parse($nonCalculatedOffDay['end_date']))) {
                    if (is_null($nonCalculatedOffDay->deleted)) {
                        return (int)0;
                    }
                }
            }

            if (Carbon::parse($date)->between($datefrom, $dateTo)) {
                foreach ($work_time_obj['work_times_array'] as $key => $work_times) {
                    if (Carbon::parse($date)->format('N') == $key) {
                        return (int)$work_times * 3600;
                    }
                }
            }
        }
    }
}
