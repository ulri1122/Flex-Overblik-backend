<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
            'email' => $request['email'],
            'image_path' => $image['path'] ?? null,
            'image_name' => $image['name'] ?? 'temp_name',
            'is_admin' => $request['is_admin'] ?? 0
        ]);

        $image = $this->uploadImage($request, $User);

        $User->image_path = $image['path'];
        $User->image_name = $image['name'];
        $User->save();
        return $User;
    }


    public function updateUser(&$user, $request)
    {
        if (!$user) {
            return "cannot_create_user_does_excist";
        }

        $image = $this->uploadImage($request, $user);
        $user->name = $request['username'] ?? $user->name;
        $user->phone = $request['phone'] ?? $user->phone;
        $user->email = $request['email'] ?? $user->email;
        $user->is_admin = $request['is_admin'] ?? $user->is_admin;
        $user->image_path = $image['path'] ?? $user->image_path;
        $user->image_name = $image['name'] ?? $user->name;
        $user->save();
    }

    public function uploadImage($request, user $user)
    {

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        if ($validator->fails()) {
            return [
                'name' => $user->image_name,
                'path' => $user->image_path
            ];
        }

        if ($file = $request->file('file')) {


            $name = $file->getClientOriginalName();
            if ($user->image_name == $name) {
                return [
                    'name' => $name,
                    'path' => $user->image_path
                ];
            }
            $path = $file->store('public/files');
            $path = str_replace('public/files/', 'storage/files/', $path);
            return [
                'name' => $name,
                'path' => $path
            ];
        }
    }
}
