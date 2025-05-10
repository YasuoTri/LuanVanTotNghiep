<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthorized. Please login first.'
            ], 401);
        }

        // Check if authenticated user has admin role
        if (Auth::user()->role !== 'admin') {
            return response()->json([
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        return $next($request);
    }
}
