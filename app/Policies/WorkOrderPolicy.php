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
     * Admin, Manager, Supervisor: can view any WO.
     * Teknisi: only their assigned WOs.
     */
    public function view(LocalUser $user, WorkOrder $workOrder): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        if ($user->isSupervisor()) {
            return true; // Supervisors can view all WOs
        }

        // Teknisi: only assigned WOs
        if ($user->isTeknisi()) {
            return $this->isAssignedToWorkOrder($user, $workOrder);
        }

        return false;
    }

    /**
     * Determine if the user can create work orders.
     * Admin, Manager, Supervisors can create.
     */
    public function create(LocalUser $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isSupervisor();
    }

    /**
     * Determine if the user can update a specific work order.
     * Admin: always.
     * Manager: always.
     * Supervisor: only WOs in their division.
     * Teknisi: only their assigned WOs (can update status, notes, times).
     */
    public function update(LocalUser $user, WorkOrder $workOrder): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        if ($user->isSupervisor()) {
            $userDivision = $user->getRoleDivision();
            return !$userDivision || $workOrder->division === $userDivision;
        }

        if ($user->isTeknisi()) {
            return $this->isAssignedToWorkOrder($user, $workOrder);
        }

        return false;
    }

    /**
     * Determine if the user can delete a work order.
     * Only Admin and Manager can delete.
     */
    public function delete(LocalUser $user, WorkOrder $workOrder): bool
    {
        return $user->isAdmin() || $user->isManager();
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
