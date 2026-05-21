<?php

namespace App\Notifications;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkOrderEditedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int,string>  $changedFields  list of field labels that changed
     *                                            (e.g. ['description', 'notes_pemberi_tugas'])
     */
    public function __construct(
        protected WorkOrder $workOrder,
        protected array $changedFields,
        protected LocalUser $editedBy
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $labels = [
            'description'        => 'deskripsi',
            'notes_pemberi_tugas' => 'catatan pemberi tugas',
        ];

        $changedLabels = array_map(
            fn ($f) => $labels[$f] ?? $f,
            $this->changedFields
        );
        $changedText = implode(' dan ', $changedLabels);

        return [
            'type'           => 'WorkOrderEdited',
            'title'          => 'Work Order Diperbarui',
            'message'        => "{$this->workOrder->wo_number}: {$changedText} diubah oleh {$this->editedBy->name}",
            'wo_id'          => $this->workOrder->id,
            'wo_number'      => $this->workOrder->wo_number,
            'changed_fields' => $this->changedFields,
            'edited_by_name' => $this->editedBy->name,
        ];
    }
}
