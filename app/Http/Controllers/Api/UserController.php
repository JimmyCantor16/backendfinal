<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::where('business_id', auth()->user()->business_id)
            ->with('roles')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'business_id' => auth()->user()->business_id,
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => $request->password,
        ]);

        $role = Role::where('name', $request->role)->first();
        $user->roles()->attach($role->id);

        return response()->json($user->load('roles'), 201);
    }

    public function update(Request $request, User $user)
    {
        if ($user->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => "sometimes|email|unique:users,email,{$user->id}",
            'password' => 'nullable|string|min:8',
            'role'     => 'sometimes|exists:roles,name',
        ]);

        $data = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        if ($request->filled('role')) {
            $role = Role::where('name', $request->role)->first();
            $user->roles()->sync([$role->id]);
        }

        return response()->json($user->load('roles'));
    }

    public function destroy(User $user)
    {
        if ($user->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $user->roles()->detach();
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }
}
