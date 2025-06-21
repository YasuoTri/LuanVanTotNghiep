<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserHasRole
{
    public function handle($request, Closure $next)
    {
        if (Auth::check() && Auth::user()->role === null) {
            return redirect()->route('select-role');
        }

        return $next($request);
    }

}