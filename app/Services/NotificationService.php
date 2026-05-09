<?php

namespace App\Services;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Notifications\WorkOrderCreatedNotification;
use App\Notifications\WorkOrderStatusChangedNotification;

class NotificationService
{
    /**
     * Notify assigned personnel when a work order is created.
     */
    public function notifyWorkOrderCreated(WorkOrder $workOrder): void
    {
        $workOrder->loadMissing(['personnel.user', 'creator']);

        // Notify all assigned personnel
        foreach ($workOrder->personnel as $assignment) {
            if ($assignment->user && $assignment->user->id !== $workOrder->created_by) {
                $assignment->user->notify(new WorkOrderCreatedNotification($workOrder));
            }
        }

        // Notify the assigned technician (for personal WOs)
        if ($workOrder->assigned_technician_id && $workOrder->assigned_technician_id !== $workOrder->created_by) {
            $technician = LocalUser::find($workOrder->assigned_technician_id);
            if ($technician) {
                $technician->notify(new WorkOrderCreatedNotification($workOrder));
            }
        }

        // Notify manager
        if ($workOrder->manager_id && $workOrder->manager_id !== $workOrder->created_by) {
            $manager = LocalUser::find($workOrder->manager_id);
            if ($manager) {
                $manager->notify(new WorkOrderCreatedNotification($workOrder));
            }
        }
    }

    /**
     * Notify relevant users when a work order status changes.
     */
    public function notifyStatusChanged(WorkOrder $workOrder, string $oldStatus, string $newStatus, LocalUser $changedBy): void
    {
        $workOrder->loadMissing(['personnel.user', 'supervisor', 'manager', 'creator']);

        $notifiedIds = [$changedBy->id]; // Don't notify the person who changed it

        // Notify the creator
        if ($workOrder->creator && !in_array($workOrder->creator->id, $notifiedIds)) {
            $workOrder->creator->notify(new WorkOrderStatusChangedNotification($workOrder, $oldStatus, $newStatus, $changedBy));
            $notifiedIds[] = $workOrder->creator->id;
        }

        // Notify the supervisor
        if ($workOrder->supervisor && !in_array($workOrder->supervisor->id, $notifiedIds)) {
            $workOrder->supervisor->notify(new WorkOrderStatusChangedNotification($workOrder, $oldStatus, $newStatus, $changedBy));
            $notifiedIds[] = $workOrder->supervisor->id;
        }

        // Notify the manager
        if ($workOrder->manager && !in_array($workOrder->manager->id, $notifiedIds)) {
            $workOrder->manager->notify(new WorkOrderStatusChangedNotification($workOrder, $oldStatus, $newStatus, $changedBy));
            $notifiedIds[] = $workOrder->manager->id;
        }

        // Notify assigned personnel
        foreach ($workOrder->personnel as $assignment) {
            if ($assignment->user && !in_array($assignment->user->id, $notifiedIds)) {
                $assignment->user->notify(new WorkOrderStatusChangedNotification($workOrder, $oldStatus, $newStatus, $changedBy));
                $notifiedIds[] = $assignment->user->id;
            }
        }
    }
}
