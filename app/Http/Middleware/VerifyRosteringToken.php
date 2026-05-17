<?php

namespace App\Http\Middleware;

use App\Services\RosteringAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerifyRosteringToken
 *
 * Production auth middleware for atoms-maintenance.
 * Validates the Bearer token against atoms-rostering's /api/auth/me endpoint.
 *
 * Used when DEV_MOCK_AUTH=false.
 *
 * Flow:
 *   1. Extract Bearer token from Authorization header OR ?token query param.
 *   2. Call RosteringAuthService::validateToken().
 *   3. If valid: build transient user, bind to Auth, proceed.
 *   4. If invalid/missing: return 401 JSON.
 */
class VerifyRosteringToken
{
    public function __construct(private RosteringAuthService $rosteringAuth) {}

    public function handle(Request $request, Closure $next): Response
    {
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

        // Build a transient user object and bind it to the Auth facade
        // so downstream controllers/policies can call Auth::user() normally.
        $transientUser = $this->rosteringAuth->buildTransientUser($rosteringUser);
        Auth::setUser($transientUser);

        // Also attach raw rostering user data to the request for convenience
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
