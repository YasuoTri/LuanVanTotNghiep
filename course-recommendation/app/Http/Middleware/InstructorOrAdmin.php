<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstructorOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}