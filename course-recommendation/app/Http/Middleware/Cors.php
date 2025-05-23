<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        // Xử lý yêu cầu OPTIONS (preflight)
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);
            $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:4200');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN');
            $response->headers->set('Access-Control-Max-Age', '86400');
            return $response;
        }

        $response = $next($request);

        // Thêm header CORS cho các yêu cầu khác
        $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:4200');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN');

        // Nếu cần hỗ trợ credentials (cookies, auth headers)
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}