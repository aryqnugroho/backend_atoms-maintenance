<?php

namespace App\Http\Middleware;

use App\Models\LocalUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MockAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (env('DEV_MOCK_AUTH', false)) {
            $token = $request->bearerToken();
            if ($token && preg_match('/^mock-token-(\d+)$/', $token, $matches)) {
                $userId = $matches[1];
                $user = LocalUser::where('rostering_user_id', $userId)->first();
                if ($user) {
                    Auth::login($user);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated mock user.',
                        'data' => null,
                        'errors' => null,
                    ], 401);
                }
            } else if (!$token && $request->is('api/v1/auth/login')) {
               // Allow pass-through for login in mock mode
            } else {
                 return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated. Mock auth requires token.',
                        'data' => null,
                        'errors' => null,
                 ], 401);
            }
        } else {
             // In production, validate Sanctum token here or defer to auth:sanctum
             if (!Auth::check()) {
                 return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated.',
                        'data' => null,
                        'errors' => null,
                 ], 401);
             }
        }

        return $next($request);
    }
}
