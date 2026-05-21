<?php

namespace App\Services;

use App\Models\Cnsd\CnsdReadinessRecord;
use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Notifications\CnsdMeterReadingCreatedNotification;
use App\Notifications\CnsdReadinessCompletedNotification;
use App\Notifications\CnsdReadinessCreatedNotification;
use App\Notifications\WorkOrderCreatedNotification;
use App\Notifications\WorkOrderEditedNotification;
use App\Notifications\WorkOrderShiftEndingReminderNotification;
use App\Notifications\WorkOrderStatusChangedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

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

    // ─── CNSD Readiness Notifications ─────────────────────────

    /**
     * Notify relevant users when a CNSD readiness record is created.
     *
     * Notifies:
     *   - Manager Teknik (if cached and different from creator)
     *   - Supervisor CNSD (if cached and different from creator)
     *   - All assigned technicians (if they have a local_users entry)
     */
    public function notifyReadinessCreated(CnsdReadinessRecord $record): void
    {
        $notifiedIds = [];

        // Exclude the creator from notifications
        if ($record->created_by_id) {
            $notifiedIds[] = $record->created_by_id;
        }

        // Notify Manager Teknik
        if ($record->manager_id && !in_array($record->manager_id, $notifiedIds)) {
            $manager = LocalUser::find($record->manager_id);
            if ($manager) {
                $manager->notify(new CnsdReadinessCreatedNotification($record));
                $notifiedIds[] = $record->manager_id;
            }
        }

        // Notify Supervisor CNSD
        if ($record->supervisor_id && !in_array($record->supervisor_id, $notifiedIds)) {
            $supervisor = LocalUser::find($record->supervisor_id);
            if ($supervisor) {
                $supervisor->notify(new CnsdReadinessCreatedNotification($record));
                $notifiedIds[] = $record->supervisor_id;
            }
        }

        // Notify all assigned technicians
        $record->loadMissing('technicians');
        foreach ($record->technicians as $techRow) {
            if (!$techRow->technician_id || in_array($techRow->technician_id, $notifiedIds)) {
                continue;
            }
            $technician = LocalUser::find($techRow->technician_id);
            if ($technician) {
                $technician->notify(new CnsdReadinessCreatedNotification($record));
                $notifiedIds[] = $techRow->technician_id;
            }
        }
    }

    /**
     * Notify relevant users when a CNSD readiness record is completed.
     *
     * Notifies the same set as create, except the signer who caused completion.
     */
    public function notifyReadinessCompleted(CnsdReadinessRecord $record, LocalUser $completedBy): void
    {
        $notifiedIds = [$completedBy->id];

        if ($record->created_by_id && !in_array($record->created_by_id, $notifiedIds)) {
            $creator = LocalUser::find($record->created_by_id);
            if ($creator) {
                $creator->notify(new CnsdReadinessCompletedNotification($record, $completedBy));
                $notifiedIds[] = $record->created_by_id;
            }
        }

        if ($record->manager_id && !in_array($record->manager_id, $notifiedIds)) {
            $manager = LocalUser::find($record->manager_id);
            if ($manager) {
                $manager->notify(new CnsdReadinessCompletedNotification($record, $completedBy));
                $notifiedIds[] = $record->manager_id;
            }
        }

        if ($record->supervisor_id && !in_array($record->supervisor_id, $notifiedIds)) {
            $supervisor = LocalUser::find($record->supervisor_id);
            if ($supervisor) {
                $supervisor->notify(new CnsdReadinessCompletedNotification($record, $completedBy));
                $notifiedIds[] = $record->supervisor_id;
            }
        }

        $record->loadMissing('technicians');
        foreach ($record->technicians as $techRow) {
            if (!$techRow->technician_id || in_array($techRow->technician_id, $notifiedIds)) {
                continue;
            }
            $technician = LocalUser::find($techRow->technician_id);
            if ($technician) {
                $technician->notify(new CnsdReadinessCompletedNotification($record, $completedBy));
                $notifiedIds[] = $techRow->technician_id;
            }
        }
    }

    // ─── Work Order Notifications ──────────────────────────────

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

    /**
     * Notify assigned personnel when a work order is edited (description/notes).
     *
     * Audience matches notifyWorkOrderCreated: assigned manager + supervisor
     * + assigned_technician + every WorkOrderPersonnel.user. The editor
     * themselves is excluded.
     *
     * @param  array<int,string>  $changedFields  field names that actually changed
     */
    public function notifyWorkOrderEdited(WorkOrder $workOrder, array $changedFields, LocalUser $editedBy): void
    {
        if (empty($changedFields)) {
            return;
        }

        $workOrder->loadMissing(['personnel.user', 'manager', 'supervisor']);

        $notifiedIds = [$editedBy->id];

        $candidates = collect();
        if ($workOrder->manager)    { $candidates->push($workOrder->manager); }
        if ($workOrder->supervisor) { $candidates->push($workOrder->supervisor); }
        if ($workOrder->assigned_technician_id) {
            $tech = LocalUser::find($workOrder->assigned_technician_id);
            if ($tech) { $candidates->push($tech); }
        }
        foreach ($workOrder->personnel as $assignment) {
            if ($assignment->user) { $candidates->push($assignment->user); }
        }

        foreach ($candidates as $user) {
            if (in_array($user->id, $notifiedIds, true)) {
                continue;
            }
            $user->notify(new WorkOrderEditedNotification($workOrder, $changedFields, $editedBy));
            $notifiedIds[] = $user->id;
        }
    }

    /**
     * Send a "shift ending in ~10 minutes" reminder to the specific role-holder
     * whose signature is still pending on the given work order.
     *
     * Idempotency: skip if the same user has already received a reminder for
     * this WO + role within the last 30 minutes. Prevents duplicates from
     * cron drift inside the [9, 11] minute sending window.
     *
     * @param  string  $role  'mt' | 'supervisor' | 'technician'
     */
    public function notifyShiftEndingUnsigned(WorkOrder $workOrder, LocalUser $user, string $role): bool
    {
        $recent = DatabaseNotification::query()
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->where('type', WorkOrderShiftEndingReminderNotification::class)
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->where('data->wo_id', $workOrder->id)
            ->where('data->role', $role)
            ->exists();

        if ($recent) {
            return false;
        }

        $user->notify(new WorkOrderShiftEndingReminderNotification($workOrder, $role));
        return true;
    }

    /**
     * Notify Manager Teknik, Supervisor CNSD, and assigned technicians when a
     * CNSD Meter Reading record is created. The record param accepts any of
     * the 12 meter-reading record models — all share manager_id / supervisor_id
     * / technicians relation / shift_type / date / form_number columns.
     *
     * @param  Model   $record     Meter reading record (any of the 12 *MeterRecord models).
     * @param  string  $facility   Human-readable facility (e.g. "ATC SYSTEM").
     * @param  string  $route      Frontend list route (e.g. "/cnsd/atc-system-meter").
     */
    public function notifyCnsdMeterReadingCreated(Model $record, string $facility, string $route, LocalUser $creator): void
    {
        $record->loadMissing('technicians');

        $notifiedIds  = [$creator->id];
        $candidateIds = [];

        if ($record->manager_id)    { $candidateIds[] = (int) $record->manager_id; }
        if ($record->supervisor_id) { $candidateIds[] = (int) $record->supervisor_id; }
        foreach ($record->technicians as $tech) {
            if ($tech->technician_id) {
                $candidateIds[] = (int) $tech->technician_id;
            }
        }

        $shiftType = (string) $record->shift_type;
        $date      = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : (string) $record->date;
        $recordId  = (int) $record->id;
        $formNum   = (string) $record->form_number;

        foreach (array_unique($candidateIds) as $userId) {
            if (in_array($userId, $notifiedIds, true)) {
                continue;
            }
            $user = LocalUser::find($userId);
            if (!$user) {
                continue;
            }
            $user->notify(new CnsdMeterReadingCreatedNotification(
                $facility, $formNum, $recordId, $route, $shiftType, $date, $creator
            ));
            $notifiedIds[] = $userId;
        }
    }
}
