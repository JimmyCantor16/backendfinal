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
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Auth"},
     *     summary="Inicia sesión con email/password + reCAPTCHA y devuelve token Sanctum.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","recaptcha_token"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@jamz.local"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="recaptcha_token", type="string", example="03AGdBq2..."),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="access_token", type="string", example="1|abc123..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *         )
     *     ),
     *     @OA\Response(response=401, description="Credenciales inválidas"),
     *     @OA\Response(response=422, description="Error de validación o reCAPTCHA fallido"),
     * )
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
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Auth"},
     *     summary="Cierra la sesión actual revocando el token Sanctum activo.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sesión cerrada correctamente")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     * )
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
     *
     * @OA\Post(
     *     path="/api/auth/verify-password",
     *     tags={"Auth"},
     *     summary="Verifica la contraseña del usuario autenticado (re-auth para acciones sensibles).",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", format="password", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña verificada",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Contraseña verificada correctamente"))
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Contraseña incorrecta o error de validación"),
     * )
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
     *
     * @OA\Get(
     *     path="/api/auth/me",
     *     tags={"Auth"},
     *     summary="Devuelve el usuario autenticado con roles y negocio.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuario autenticado",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     * )
     */
    public function me(Request $request)
    {
        return response()->json(
            $request->user()->load(['roles', 'business'])
        );
    }

}
