<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// LOGIN (público)
Route::post('/login', [AuthController::class, 'login']);

// RUTAS PROTEGIDAS
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/users', [UserController::class, 'index']);

    Route::post('/logout', [AuthController::class, 'logout']);

});
