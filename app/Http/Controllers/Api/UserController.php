<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        // Solo devolvemos id, name y email (nunca password)
        return response()->json(User::select('id','name','email')->get());
    }
}
