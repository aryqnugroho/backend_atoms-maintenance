<?php

namespace App\Notifications;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkOrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected WorkOrder $workOrder,
        protected string $oldStatus,
        protected string $newStatus,
        protected LocalUser $changedBy
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray(object $notifiable): array
    {
        $statusLabels = [
            'ongoing' => 'Ongoing',
            'on_hold' => 'On Hold',
            'completed' => 'Completed',
        ];

        $newLabel = $statusLabels[$this->newStatus] ?? $this->newStatus;

        return [
            'type' => 'WorkOrderStatusChanged',
            'title' => 'Status WO Berubah',
            'message' => "{$this->workOrder->wo_number} status berubah menjadi {$newLabel}" .
                " oleh {$this->changedBy->name}",
            'wo_id' => $this->workOrder->id,
            'wo_number' => $this->workOrder->wo_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'changed_by_name' => $this->changedBy->name,
        ];
    }
}
