<?php

namespace App\Policies;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;

class WorkOrderPolicy
{
    /**
     * Determine if the user can view any work orders.
     * All authenticated users can list (Teknisi visibility is filtered in the service).
     */
    public function viewAny(LocalUser $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific work order.
     * Admin, Manager, General Manager, Supervisor: any WO (full visibility).
     * Teknisi: any WO IN THEIR DIVISION (CNSD teknisi sees CNSD WOs, etc.).
     *   Edit/feedback is still gated to assigned-only by `update`.
     */
    public function view(LocalUser $user, WorkOrder $workOrder): bool
    {
        if ($user->isAdmin() || $user->isManager() || $user->isGeneralManager()) {
            return true;
        }

        if ($user->isSupervisor()) {
            return true; // Supervisors are MT-equivalent: full cross-division view
        }

        if ($user->isTeknisi()) {
            $division = $user->getRoleDivision();
            return $division !== null && $workOrder->division === $division;
        }

        return false;
    }

    /**
     * Determine if the user can create work orders.
     * Admin, Manager, General Manager (gm_directive only), Supervisors can create.
     * Teknisi cannot create.
     */
    public function create(LocalUser $user): bool
    {
        return $user->isAdmin()
            || $user->isManager()
            || $user->isGeneralManager()
            || $user->isSupervisor();
    }

    /**
     * Determine if the user can update a specific work order.
     * Admin / Manager Teknik / Supervisor: full access (supervisors are
     *   MT-equivalent across divisions).
     * General Manager: only their own gm_directive WOs while still ongoing.
     * Teknisi: only WOs they are assigned to — feedback workflow remains
     *   personal even though their list view is widened to the whole division.
     */
    public function update(LocalUser $user, WorkOrder $workOrder): bool
    {
        if ($user->isAdmin() || $user->isManager() || $user->isSupervisor()) {
            return true;
        }

        if ($user->isGeneralManager()) {
            return $workOrder->wo_type === 'gm_directive'
                && $workOrder->created_by === $user->id
                && $workOrder->status === 'ongoing';
        }

        if ($user->isTeknisi()) {
            return $this->isAssignedToWorkOrder($user, $workOrder);
        }

        return false;
    }

    /**
     * Determine if the user can delete a work order.
     * Admin / Manager Teknik / Supervisor: any WO.
     * General Manager: only their own gm_directive WOs while still ongoing.
     */
    public function delete(LocalUser $user, WorkOrder $workOrder): bool
    {
        if ($user->isAdmin() || $user->isManager() || $user->isSupervisor()) {
            return true;
        }

        if ($user->isGeneralManager()) {
            return $workOrder->wo_type === 'gm_directive'
                && $workOrder->created_by === $user->id
                && $workOrder->status === 'ongoing';
        }

        return false;
    }

    /**
     * Check if a user is assigned to a work order (as personnel or assigned technician).
     */
    private function isAssignedToWorkOrder(LocalUser $user, WorkOrder $workOrder): bool
    {
        // Check if user is the assigned technician (for personal WOs)
        if ($workOrder->assigned_technician_id === $user->id) {
            return true;
        }

        // Check if user is in the personnel list
        return $workOrder->personnel()
            ->where('user_id', $user->id)
            ->exists();
    }
}
