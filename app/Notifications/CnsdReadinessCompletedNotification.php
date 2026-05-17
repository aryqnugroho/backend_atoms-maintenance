<?php

namespace App\Notifications;

use App\Models\Cnsd\CnsdReadinessRecord;
use App\Models\LocalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired when a CNSD readiness record transitions to 'completed'
 * (i.e. all required signatures — manager, supervisor if present,
 * and every technician — have been submitted).
 *
 * Sent via the 'database' channel.
 */
class CnsdReadinessCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected CnsdReadinessRecord $record,
        protected LocalUser $completedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'CnsdReadinessCompleted',
            'title'             => 'Kesiapan Peralatan Selesai',
            'message'           => sprintf(
                'Form %s (%s shift %s) telah lengkap ditandatangani.',
                $this->record->form_number,
                $this->record->date?->format('d/m/Y') ?? '',
                strtoupper($this->record->shift_type),
            ),
            'cnsd_readiness_id' => $this->record->id,
            'form_number'       => $this->record->form_number,
            'date'              => $this->record->date?->format('Y-m-d'),
            'shift_type'        => $this->record->shift_type,
            'completed_by_name' => $this->completedBy->name,
        ];
    }
}
