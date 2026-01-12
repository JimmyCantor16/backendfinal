<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;

// 🔓 LOGIN PÚBLICO
Route::post('/login', [AuthController::class, 'login']);

// 🔐 RUTAS PROTEGIDAS CON SANCTUM
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
});

Route::get('/create-admin', [AuthController::class, 'createAdmin']);

