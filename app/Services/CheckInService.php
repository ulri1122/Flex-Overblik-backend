<?php

namespace App\Services;

use App\Http\Controllers\UserController;
use App\Models\CheckIn;
use App\Models\NfcCards;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class CheckInService.
 */
class CheckInService
{
    public function checkIn($request)
    {


        $nfc_card = NfcCards::where('card_id', $request['card_id'])->where('in_use', NfcCards::IN_USE)->first();

        if (!$nfc_card) {
            return ['error' => 'card_does_not_excist'];
        }

        if (!$nfc_card->user) {
            return ['error' => 'user_does_not_excist'];
        }
        $checkInTypeRow = CheckIn::where('user_id', $nfc_card['user_id'])->latest()->first();

        if ($checkInTypeRow && is_null($checkInTypeRow['checked_out'])) {
            $checkInTypeRow->checked_out = Carbon::now();
            $checkInTypeRow->calculated = 0;
            $checkInTypeRow->save();
            return $checkInTypeRow;
        } else {
            return CheckIn::create([
                'user_id' => $nfc_card->user->id,
                'check_in_type' => $request['check_in_type'],
                'checked_in' => Carbon::now()
            ]);
        }
    }
    public function checkInWithUserId($request)
    {
        $user = User::where('id', $request['user_id'])->first();
        if (!$user) {
            return ['error' => 'user_does_not_excist'];
        }

        $checkInTypeRow = CheckIn::where('user_id', $user->id)->latest()->first();

        if ($checkInTypeRow && is_null($checkInTypeRow['checked_out'])) {
            $checkInTypeRow->checked_out = Carbon::now();
            $checkInTypeRow->calculated = 0;
            $checkInTypeRow->save();
            return $checkInTypeRow;
        } else {
            return CheckIn::create([
                'user_id' => $user->id,
                'check_in_type' => $request['check_in_type'],
                'checked_in' => Carbon::now()
            ]);
        }
    }
    public function getTimeStamps(int $user_id, $take = "*")
    {
        if ($take == "*") {
            return CheckIn::where('user_id', $user_id)->get();
        } else {
            return CheckIn::where('user_id', $user_id)->take($take)->orderBy('id', 'desc')->get();
        }
    }
}
