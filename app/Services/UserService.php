<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Class userService.
 */
class UserService
{
    public function createUser($request): User
    {

        $User = User::where('id', $request['user_id'])->first();

        if ($User) {
            return "cannot_create_user_does_excist";
        }

        $User = User::create([
            'name' => $request['username'],
            'phone' => $request['phone'],
            'email' => $request['email']
        ]);
        return $User;
    }


    public function updateUser(&$user, $request)
    {
        if (!$user) {
            return "cannot_create_user_does_excist";
        }
        $user->name = $request['username'] ?? $user->name;
        $user->phone = $request['phone'] ?? $user->phone;
        $user->email = $request['email'] ?? $user->email;
        $user->save();
    }
}
