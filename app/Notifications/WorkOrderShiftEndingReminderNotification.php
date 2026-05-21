<?php

namespace App\Notifications;

use App\Models\WorkOrder\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkOrderShiftEndingReminderNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $role  'mt' | 'supervisor' | 'technician' — the role
     *                        whose signature is still pending for the recipient.
     */
    public function __construct(
        protected WorkOrder $workOrder,
        protected string $role
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $roleLabels = [
            'mt'         => 'Manager Teknik',
            'supervisor' => 'Supervisor',
            'technician' => 'Teknisi',
        ];
        $roleLabel = $roleLabels[$this->role] ?? $this->role;

        $shiftLabel = ucfirst($this->workOrder->shift_type ?? '');

        return [
            'type'      => 'WorkOrderShiftEndingReminder',
            'title'     => 'Pengingat Tanda Tangan WO',
            'message'   => "Shift {$shiftLabel} segera berakhir (~10 menit). "
                . "Anda belum menandatangani {$this->workOrder->wo_number} sebagai {$roleLabel}.",
            'wo_id'     => $this->workOrder->id,
            'wo_number' => $this->workOrder->wo_number,
            'role'      => $this->role,
            'shift_type' => $this->workOrder->shift_type,
            'shift_date' => $this->workOrder->shift_date?->format('Y-m-d'),
        ];
    }
}
