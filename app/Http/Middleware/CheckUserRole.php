<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->hasRole($role)) {
            return response()->json([
                'message' => "Access denied. Required role: {$role}",
                'user_role' => $user->getRoleNames(),
            ], 403);
        }

        return $next($request);
    }
}