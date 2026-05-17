<?php

namespace App\Http\Middleware;

use App\Models\LocalUser;
use App\Services\RosteringAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * MockAuthMiddleware
 *
 * Unified auth middleware for atoms-maintenance.
 *
 * DEV_MOCK_AUTH=true  → Mock mode: accepts mock-token-{user_id} pattern,
 *                        resolves user from local_users table.
 *                        Used during development without atoms-rostering running.
 *
 * DEV_MOCK_AUTH=false → Production mode: delegates to VerifyRosteringToken logic,
 *                        validates Bearer token against atoms-rostering /api/auth/me.
 */
class MockAuthMiddleware
{
    public function __construct(private RosteringAuthService $rosteringAuth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (env('DEV_MOCK_AUTH', false)) {
            return $this->handleMockAuth($request, $next);
        }

        return $this->handleRosteringAuth($request, $next);
    }

    // ─── Mock Auth (DEV_MOCK_AUTH=true) ──────────────────────────────────────

    private function handleMockAuth(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        // Allow login endpoint to pass through without a token
        if (!$token && $request->is('api/v1/auth/login')) {
            return $next($request);
        }

        if (!$token) {
            return $this->unauthorized('Unauthenticated. Mock auth requires token.');
        }

        if (!preg_match('/^mock-token-(\d+)$/', $token, $matches)) {
            return $this->unauthorized('Unauthenticated. Invalid mock token format.');
        }

        $userId = $matches[1];
        $user = LocalUser::where('rostering_user_id', $userId)->first();

        if (!$user) {
            return $this->unauthorized('Unauthenticated mock user.');
        }

        Auth::login($user);
        return $next($request);
    }

    // ─── Rostering Auth (DEV_MOCK_AUTH=false) ────────────────────────────────

    private function handleRosteringAuth(Request $request, Closure $next): Response
    {
        // Allow verify endpoint to pass through (it handles its own auth internally)
        if ($request->is('api/v1/auth/verify')) {
            return $next($request);
        }

        // Accept token from Authorization header or ?token query param
        $token = $request->bearerToken() ?? $request->query('token');

        if (!$token) {
            return $this->unauthorized('No authentication token provided.');
        }

        $rosteringUser = $this->rosteringAuth->validateToken($token);

        if (!$rosteringUser) {
            return $this->unauthorized('Token is invalid or atoms-rostering is unreachable.');
        }

        if (empty($rosteringUser['is_active'])) {
            return $this->unauthorized('Account is not active.');
        }

        $transientUser = $this->rosteringAuth->buildTransientUser($rosteringUser);
        Auth::setUser($transientUser);
        $request->merge(['_rostering_user' => $rosteringUser]);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => null,
        ], 401);
    }
}
