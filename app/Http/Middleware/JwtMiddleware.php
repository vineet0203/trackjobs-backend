<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'timestamp' => now()->toISOString(),
                    'code' => 401,
                    'error_code' => 'USER_NOT_FOUND'
                ], 401);
            }

            // Check if user is active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is deactivated',
                    'timestamp' => now()->toISOString(),
                    'code' => 403,
                    'error_code' => 'ACCOUNT_DEACTIVATED'
                ], 403);
            }

            // Check if company is active (if user belongs to a company)
            if ($user->company_id && (!$user->company->is_active || $user->company->status !== 'approved')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company account is not active',
                    'timestamp' => now()->toISOString(),
                    'code' => 403,
                    'error_code' => 'COMPANY_INACTIVE'
                ], 403);
            }

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired. Please log in again.',
                'timestamp' => now()->toISOString(),
                'code' => 401,
                'error_code' => 'TOKEN_EXPIRED'
            ], 401);
            
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token. Token signature could not be verified.',
                'timestamp' => now()->toISOString(),
                'code' => 401,
                'error_code' => 'TOKEN_INVALID'
            ], 401);
            
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required.',
                'timestamp' => now()->toISOString(),
                'code' => 401,
                'error_code' => 'TOKEN_ABSENT'
            ], 401);
        }

        return $next($request);
    }
}