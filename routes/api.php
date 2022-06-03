<?php

use App\Http\Controllers\CheckInController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//users.
Route::get('/getUserProfile', [UserController::class, 'getUserProfile']);
Route::post('/users', [UserController::class, 'createUser']);



//checkIn
Route::post('/check_in', [CheckInController::class, 'checkIn']);


//team
Route::post('/createTeam', [TeamController::class, 'createTeam']);
Route::get('/getTeams', [TeamController::class, 'getTeams']);
