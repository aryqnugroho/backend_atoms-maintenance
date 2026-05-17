<?php

namespace App\Notifications;

use App\Models\Cnsd\CnsdReadinessRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired when a new CNSD Equipment Readiness record (Form EQ-1) is created.
 *
 * Sent via the 'database' channel so it surfaces in the in-app
 * notification bell — same mechanism as Work Order notifications.
 */
class CnsdReadinessCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected CnsdReadinessRecord $record,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'CnsdReadinessCreated',
            'title'            => 'Kesiapan Peralatan Baru',
            'message'          => sprintf(
                'Form %s (%s shift %s) telah dibuat%s.',
                $this->record->form_number,
                $this->record->date?->format('d/m/Y') ?? '',
                strtoupper($this->record->shift_type),
                $this->record->created_by_name ? " oleh {$this->record->created_by_name}" : '',
            ),
            'cnsd_readiness_id' => $this->record->id,
            'form_number'       => $this->record->form_number,
            'date'              => $this->record->date?->format('Y-m-d'),
            'shift_type'        => $this->record->shift_type,
            'created_by_name'   => $this->record->created_by_name,
        ];
    }
}
