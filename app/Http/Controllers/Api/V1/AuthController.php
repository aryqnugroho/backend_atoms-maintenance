<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LocalUser;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Login endpoint.
     * In dev (mock mode), this may just return a mock token for the requested role/user.
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
                'token_type' => 'Bearer',
                'user' => $user,
            ], 'Mock login successful');
        }

        // Production SSO login logic to atoms-rostering would go here
        return $this->error('Not implemented for production yet.', null, 501);
    }

    /**
     * Get current user.
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
     * Logout endpoint.
     */
    public function logout(Request $request)
    {
        if (env('DEV_MOCK_AUTH', false)) {
            // Mock logout
            Auth::logout();
            return $this->success(null, 'Mock logout successful');
        }

        // Production SSO logout
        return $this->error('Not implemented for production yet.', null, 501);
    }
}
