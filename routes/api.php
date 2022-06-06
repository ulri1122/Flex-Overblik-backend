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
Route::post('/users', [UserController::class, 'createUser'])->middleware(['auth:sanctum']);

Route::post('/tokens/create', [UserController::class, 'createUserToken']);
Route::get('/tokens/revoke', [UserController::class, 'revokeUserToken'])->middleware(['auth:sanctum']);
Route::get('/getUserForUpdate', [UserController::class, 'getUserForUpdate']);

Route::post('/removeUserFromTeam', [UserController::class, 'removeUserFromTeam'])->middleware(['auth:sanctum']);




//checkIn
Route::post('/check_in', [CheckInController::class, 'checkIn']);


//team
Route::post('/createTeam', [TeamController::class, 'createTeam'])->middleware(['auth:sanctum']);
Route::post('/deleteTeam', [TeamController::class, 'deleteTeam'])->middleware(['auth:sanctum']);
Route::post('/updateTeamName', [TeamController::class, 'updateTeamName'])->middleware(['auth:sanctum']);
Route::get('/getTeamsWidthUsers', [TeamController::class, 'getTeamsWidthUsers']);
Route::get('/getTeams', [TeamController::class, 'getTeams']);
