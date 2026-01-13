<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // SOLO ADMIN
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
    });
});

