<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LocalUser;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    use ApiResponse;

    /**
     * List all active personnel (for form dropdowns).
     * Returns basic user data needed for work order personnel assignment.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LocalUser::where('is_active', true);

        // Optional filter by division
        if ($request->has('division')) {
            $query->where('division', $request->input('division'));
        }

        // Optional filter by role
        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        $personnel = $query->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'division']);

        return $this->success($personnel, 'Personnel retrieved successfully');
    }
}
