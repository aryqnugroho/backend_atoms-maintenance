<?php

namespace App\Notifications;

use App\Models\LocalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CnsdMeterReadingCreatedNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $facility     Human-readable facility label (e.g. "ATC SYSTEM").
     * @param  string  $formNumber   Form number for display (e.g. "MR-ATC-...-001").
     * @param  int     $recordId     Detail record id for deep-link.
     * @param  string  $route        Frontend detail route (e.g. "/cnsd/atc-system-meter").
     * @param  string  $shiftType    Shift label (pagi|siang|malam) for context.
     * @param  string  $date         Y-m-d for context line.
     */
    public function __construct(
        protected string $facility,
        protected string $formNumber,
        protected int $recordId,
        protected string $route,
        protected string $shiftType,
        protected string $date,
        protected LocalUser $createdBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $shiftLabel = ucfirst($this->shiftType);

        return [
            'type'       => 'CnsdMeterReadingCreated',
            'title'      => 'Meter Reading Baru',
            'message'    => "Meter Reading {$this->facility} (Shift {$shiftLabel}) dibuat oleh {$this->createdBy->name}",
            // Deep-link target consumed by Topbar/Dashboard click handlers.
            'route'      => rtrim($this->route, '/') . '/' . $this->recordId,
            'record_id'  => $this->recordId,
            'facility'   => $this->facility,
            'form_number' => $this->formNumber,
            'shift_type' => $this->shiftType,
            'date'       => $this->date,
            'created_by_name' => $this->createdBy->name,
        ];
    }
}
