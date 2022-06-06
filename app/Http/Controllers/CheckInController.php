<?php

namespace App\Http\Controllers;

use App\Services\CheckInService;
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
        $user = $UserController->getUser($respose->user_id);
        return array(
            'current_flex' => $UserController->getCurrentFlexBalance($user),
            'checkin_status' => $UserController->getStatus($user),
        );
    }
}
