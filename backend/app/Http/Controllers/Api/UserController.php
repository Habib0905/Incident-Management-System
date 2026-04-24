<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'role', 'created_at']);

        return response()->json(['users' => $users]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'sometimes|in:admin,engineer',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'] ?? 'engineer',
        ]);

        return response()->json(['user' => $user], 201);
    }

    public function destroy(User $user)
    {
        if ($user->isAdmin()) {
            return response()->json(['error' => 'Cannot delete admin users'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}