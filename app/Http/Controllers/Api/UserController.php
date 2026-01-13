<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        // Devuelve todos los usuarios en JSON
        $users = User::all();
        return response()->json($users); // Esto es importante
    }
}
