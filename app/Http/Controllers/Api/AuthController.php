<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected RecaptchaService $recaptcha;

    public function __construct(RecaptchaService $recaptcha)
    {
        $this->recaptcha = $recaptcha;
    }

    /**
     * 🔓 LOGIN (con reCAPTCHA + roles)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'            => 'required|email',
            'password'         => 'required',
            'recaptcha_token'  => 'required'
        ]);

        //dd($request->recaptcha_token);

        // 🛡️ Validar reCAPTCHA
        $isHuman = $this->recaptcha->verify(
            $request->recaptcha_token,
            'login' // 👈 ACTION OBLIGATORIA
        );
        
        if (!$isHuman) {
            return response()->json([
                'message' => 'Validación reCAPTCHA fallida'
            ], 422);
        }

        // 🔐 Validar credenciales
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        // 🔑 Crear token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'          => $user->load(['roles', 'business']),
            'access_token' => $token,
            'token_type'   => 'Bearer'
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
     * 🔑 VERIFICAR CONTRASEÑA (usuario autenticado)
     */
    public function verifyPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json([
                'message' => 'Contraseña incorrecta'
            ], 422);
        }

        return response()->json([
            'message' => 'Contraseña verificada correctamente'
        ]);
    }

    /**
     * 👤 USUARIO AUTENTICADO
     */
    public function me(Request $request)
    {
        return response()->json(
            $request->user()->load(['roles', 'business'])
        );
    }

}
