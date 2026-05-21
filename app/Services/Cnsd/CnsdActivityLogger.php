<?php

namespace App\Services\Cnsd;

use App\Models\LocalUser;
use App\Models\Logbook\LogbookCnsd;
use App\Services\Logbook\LogbookCnsdService;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * CnsdActivityLogger — side-effect runner triggered after a CNSD form is
 * created (Readiness EQ-1 or any of the 12 Meter Readings).
 *
 * Two responsibilities:
 *   1. Append an "[Auto] Meter Reading <FACILITY> oleh <NAME>" note to the
 *      CNSD logbook for that date+shift. Auto-creates the daily logbook if
 *      none exists yet, so technicians don't have to set it up first.
 *   2. Fan out a CnsdMeterReadingCreated notification to the assigned
 *      manager + supervisor + technicians (creator excluded).
 *
 * Both side-effects are wrapped in try/catch by callers so that a logbook
 * write failure NEVER breaks the form-create HTTP response.
 */
class CnsdActivityLogger
{
    public function __construct(
        protected NotificationService $notificationService,
        protected LogbookCnsdService $logbookService,
    ) {}

    /**
     * Run both side-effects for a freshly-created meter reading record.
     *
     * @param  Model   $record    Any *MeterRecord with date/shift_type/form_number/manager_id/etc.
     * @param  string  $facility  Human-readable facility label (e.g. "ATC SYSTEM").
     * @param  string  $route     Frontend list route, e.g. "/cnsd/atc-system-meter".
     */
    public function logMeterReadingCreated(Model $record, string $facility, string $route, LocalUser $creator): void
    {
        $date  = $this->dateString($record->date);
        $shift = (string) $record->shift_type;

        $this->appendLogbookNote($facility, $date, $shift, $creator);

        try {
            $this->notificationService->notifyCnsdMeterReadingCreated($record, $facility, $route, $creator);
        } catch (\Throwable $e) {
            Log::warning('CnsdActivityLogger: notification dispatch failed', [
                'facility' => $facility, 'record_id' => $record->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Append the auto-note without firing notifications. Used by the readiness
     * controller, which already runs its own notification flow.
     */
    public function appendLogbookNote(string $facility, string $date, string $shift, LocalUser $creator): void
    {
        try {
            $logbook = $this->ensureLogbook($date, $creator);
            if (!$logbook) {
                return;
            }

            $logbook->notes()->create([
                'shift'    => $shift,
                'time'     => now()->format('H:i'),
                'activity' => "[Auto] Meter Reading {$facility} oleh {$creator->name}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('CnsdActivityLogger: appendLogbookNote failed', [
                'facility' => $facility, 'date' => $date, 'shift' => $shift, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch the logbook for the given date or auto-create it. Returns null if
     * even creation fails (e.g. concurrent insert lost both ways — extremely
     * unlikely but should be silent rather than 500).
     */
    private function ensureLogbook(string $date, LocalUser $creator): ?LogbookCnsd
    {
        $existing = LogbookCnsd::whereDate('date', $date)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return $this->logbookService->createLogbook($date, $creator);
        } catch (\Throwable) {
            // Race condition: another request might have just created it.
            return LogbookCnsd::whereDate('date', $date)->first();
        }
    }

    private function dateString(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        return substr((string) $date, 0, 10);
    }
}
