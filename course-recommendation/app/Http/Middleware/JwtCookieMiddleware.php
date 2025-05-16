<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class JwtCookieMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Kiểm tra token từ header
        try {
            if (JWTAuth::parseToken()->authenticate()) {
                return $next($request);
            }
        } catch (TokenInvalidException $e) {
            // Token không hợp lệ, tiếp tục kiểm tra cookie
        } catch (TokenExpiredException $e) {
            // Token hết hạn
            return response()->json(['error' => 'Token expired'], 401);
        } catch (\Exception $e) {
            // Không có token trong header, tiếp tục kiểm tra cookie
        }

        // Kiểm tra token từ cookie
        $token = $request->cookie('jwt_token');
        if ($token) {
            try {
                JWTAuth::setToken($token);
                if (JWTAuth::authenticate()) {
                    // Gắn token vào header để middleware auth:api hoạt động
                    $request->headers->set('Authorization', 'Bearer ' . $token);
                    return $next($request);
                }
            } catch (TokenInvalidException $e) {
                return response()->json(['error' => 'Invalid token'], 401);
            } catch (TokenExpiredException $e) {
                return response()->json(['error' => 'Token expired'], 401);
            }
        }

        return response()->json(['error' => 'Unauthenticated'], 401);
    }
}