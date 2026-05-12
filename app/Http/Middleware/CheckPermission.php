<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (!$request->user() || !$request->user()->can($permission)) {
            return response()->json([
                'message' => 'Forbidden. Required permission: ' . $permission,
            ], 403);
        }

        return $next($request);
    }
}