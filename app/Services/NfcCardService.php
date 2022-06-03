<?php

namespace App\Services;

use App\Models\NfcCards;
use App\Models\User;

/**
 * Class NfcCardService.
 */
class NfcCardService
{
    public function createCard(User &$User, $card_id)
    {
        if (!$User) {
            return response(['error' => 'user_does_not_excist'], 404);
        }
        NfcCards::create([
            'user_id' => $User->id,
            'card_id' => $card_id,
            'in_use' => NfcCards::IN_USE
        ]);
    }
    public function getNfcCard($card_id = 0): NfcCards|null
    {
        $NfcCards = new NfcCards();
        $queried_user = $NfcCards->where('card_id', $card_id)->first();
        return $queried_user;
    }
}
