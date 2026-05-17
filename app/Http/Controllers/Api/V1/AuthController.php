<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LocalUser;
use App\Services\RosteringAuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private RosteringAuthService $rosteringAuth) {}

    /**
     * POST /api/v1/auth/login
     *
     * Dev mock mode only. In production, login happens at atoms-rostering.
     * atoms-maintenance never handles passwords.
     */
    public function login(Request $request)
    {
        if (env('DEV_MOCK_AUTH', false)) {
            $email = $request->input('email');
            $user = LocalUser::where('email', $email)->first();

            if (!$user) {
                return $this->error('Mock user not found.', null, 404);
            }

            return $this->success([
                'access_token' => 'mock-token-' . $user->rostering_user_id,
                'token_type'   => 'Bearer',
                'user'         => $user,
            ], 'Mock login successful');
        }

        // Production: login is handled by atoms-rostering.
        // This endpoint should not be called in production.
        return $this->error(
            'Login is handled by atoms-rostering. Please authenticate there first.',
            null,
            501
        );
    }

    /**
     * GET /api/v1/auth/verify
     *
     * Called by atoms-maintenance frontend on app load to validate a Sanctum token
     * received via URL query param (?token=...) from atoms-rostering.
     *
     * In mock mode: validates mock-token-{id} pattern against local_users.
     * In production: proxies to atoms-rostering GET /api/auth/me.
     *
     * Returns the resolved user data so the frontend can populate AuthContext.
     *
     * This endpoint is PUBLIC (no auth middleware) — it IS the auth check.
     */
    public function verify(Request $request)
    {
        $token = $request->bearerToken() ?? $request->query('token');

        if (!$token) {
            return $this->error('No token provided.', null, 401);
        }

        // ── Mock mode ──────────────────────────────────────────────────────
        if (env('DEV_MOCK_AUTH', false)) {
            if (!preg_match('/^mock-token-(\d+)$/', $token, $matches)) {
                return $this->error('Invalid mock token.', null, 401);
            }

            $user = LocalUser::where('rostering_user_id', $matches[1])->first();
            if (!$user) {
                return $this->error('Mock user not found.', null, 401);
            }

            return $this->success([
                'valid' => true,
                'user'  => [
                    'id'       => $user->rostering_user_id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'role'     => $user->role,
                    'division' => $user->division,
                ],
            ], 'Token valid (mock)');
        }

        // ── Production mode ────────────────────────────────────────────────
        $rosteringUser = $this->rosteringAuth->validateToken($token);

        if (!$rosteringUser) {
            return $this->error('Token is invalid or atoms-rostering is unreachable.', null, 401);
        }

        if (empty($rosteringUser['is_active'])) {
            return $this->error('Account is not active.', null, 403);
        }

        $transientUser = $this->rosteringAuth->buildTransientUser($rosteringUser);

        return $this->success([
            'valid' => true,
            'user'  => [
                'id'       => $transientUser->id,
                'name'     => $transientUser->name,
                'email'    => $transientUser->email,
                'role'     => $transientUser->role,
                'division' => $transientUser->division,
                'grade'    => $transientUser->grade,
                'employee' => $transientUser->employee,
            ],
        ], 'Token valid');
    }

    /**
     * GET /api/v1/auth/me
     *
     * Returns the currently authenticated user.
     * Works in both mock and production modes (user is set by middleware).
     */
    public function me(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthenticated.', null, 401);
        }

        return $this->success(['user' => $user], 'Current user data');
    }

    /**
     * POST /api/v1/auth/logout
     *
     * In mock mode: clears local auth state.
     * In production: frontend should call atoms-rostering logout directly.
     *               This endpoint just acknowledges the request.
     */
    public function logout(Request $request)
    {
        if (env('DEV_MOCK_AUTH', false)) {
            Auth::logout();
            return $this->success(null, 'Mock logout successful');
        }

        // Production: actual token revocation happens at atoms-rostering.
        // atoms-maintenance has no token store to clear.
        return $this->success(null, 'Logged out. Revoke token at atoms-rostering.');
    }
}
