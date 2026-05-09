<?php

namespace App\Notifications;

use App\Models\WorkOrder\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkOrderCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected WorkOrder $workOrder
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
        return [
            'type' => 'WorkOrderCreated',
            'title' => 'Work Order Baru',
            'message' => "{$this->workOrder->wo_number} telah dibuat" .
                ($this->workOrder->creator ? " oleh {$this->workOrder->creator->name}" : ''),
            'wo_id' => $this->workOrder->id,
            'wo_number' => $this->workOrder->wo_number,
            'division' => $this->workOrder->division,
            'created_by_name' => $this->workOrder->creator?->name,
        ];
    }
}
