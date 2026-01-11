<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'JWT Guard funciona en ProfileController',
            'user' => auth()->user(), // si tu guard autentica el usuario
            'timestamp' => now()
        ]);
    }
}
