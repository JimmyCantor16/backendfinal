<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí van todas las rutas de tu API.
|
*/

// Ruta de ejemplo de usuario autenticado con Sanctum (puedes dejarla)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Lista de usuarios
Route::get('/users', [UserController::class, 'index']);

// Login rápido (sin Sanctum, para pruebas)
Route::post('/login', function(Request $request){
    $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    $user = User::where('email', $request->email)->first();
    if(!$user || !Hash::check($request->password, $user->password)){
        return response()->json(['message'=>'Credenciales incorrectas'], 401);
    }

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email
    ]);
});


// Lista de usuarios
Route::get('/users', [UserController::class, 'index']);
