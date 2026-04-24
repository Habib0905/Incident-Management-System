<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json(['error' => 'Unauthorized - insufficient permissions'], 403);
        }

        return $next($request);
    }
}