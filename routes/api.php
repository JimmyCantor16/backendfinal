<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 🔓 LOGIN (público)
Route::post('/login', [AuthController::class, 'login']);

// 🔐 RUTAS PROTEGIDAS (SANCTUM)
Route::middleware('auth:sanctum')->group(function () {

    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/users', [UserController::class, 'index']);

    // Ruta de prueba protegida
    Route::get('/test-guard', function () {
        return response()->json([
            'message' => 'Sanctum funciona correctamente',
            'user' => auth()->user()
        ]);
    });

});
