<?php

namespace App\Http\Controllers\Api\V1\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder\WorkOrder;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of work orders.
     */
    public function index()
    {
        // Skeleton implementation
        return $this->success([], 'Work orders retrieved successfully (Skeleton)');
    }

    /**
     * Store a newly created work order.
     */
    public function store(Request $request)
    {
        // Skeleton implementation
        return $this->success([], 'Work order created successfully (Skeleton)', 201);
    }

    /**
     * Display the specified work order.
     */
    public function show($id)
    {
        // Skeleton implementation
        return $this->success(['id' => $id], 'Work order retrieved successfully (Skeleton)');
    }

    /**
     * Update the specified work order.
     */
    public function update(Request $request, $id)
    {
        // Skeleton implementation
        return $this->success(['id' => $id], 'Work order updated successfully (Skeleton)');
    }

    /**
     * Remove the specified work order.
     */
    public function destroy($id)
    {
        // Skeleton implementation
        return $this->success(null, 'Work order deleted successfully (Skeleton)');
    }
}
