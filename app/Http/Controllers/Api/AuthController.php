<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * 🔓 LOGIN
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        // Crear token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'         => $user->load('roles'),
            'access_token'=> $token,
            'token_type'  => 'Bearer'
        ]);
    }

    /**
     * 🔐 LOGOUT (revoca SOLO el token actual)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    /**
     * 👤 USUARIO AUTENTICADO
     */
    public function me(Request $request)
    {
        return response()->json(
            $request->user()->load('roles')
        );
    }

    /**
     * 🛠️ CREAR ADMIN (solo para setup inicial)
     * URL: /api/create-admin
     */
    public function createAdmin()
    {
        // 1️⃣ Crear o actualizar usuario admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        // 2️⃣ Buscar rol admin
        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            return response()->json([
                'message' => 'El rol admin no existe. Ejecuta el seeder de roles.'
            ], 500);
        }

        // 3️⃣ Asignar rol si no lo tiene
        if (!$admin->roles()->where('name', 'admin')->exists()) {
            $admin->roles()->attach($adminRole->id);
        }

        return response()->json([
            'message' => 'Admin creado y rol asignado correctamente',
            'user'    => $admin->load('roles')
        ]);
    }
}
