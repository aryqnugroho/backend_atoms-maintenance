<?php

namespace App\Services\Tfp;

use App\Models\LocalUser;
use App\Models\Logbook\LogbookTfp;
use App\Services\Logbook\LogbookTfpService;
use Illuminate\Support\Facades\Log;

/**
 * TfpActivityLogger — side-effect runner triggered after a TFP Performance
 * Check form is created (AOB Ground / AOB Lt 1&2 / Transmitter TX / Radar /
 * Tower / DVOR / Localizer / Glide Path).
 *
 * Appends an "[Auto] Performance Check <FACILITY> oleh <NAME>" note to the
 * daily TFP logbook for that date+shift. Auto-creates the daily logbook if
 * none exists yet, so technicians don't have to set it up first.
 *
 * Mirrors CnsdActivityLogger but writes to logbook_tfps instead of logbook_cnsds.
 *
 * The side-effect is wrapped in try/catch by callers so that a logbook write
 * failure NEVER breaks the form-create HTTP response.
 */
class TfpActivityLogger
{
    public function __construct(
        protected LogbookTfpService $logbookService,
    ) {}

    /**
     * Append the auto-note to the TFP logbook for the given date.
     *
     * @param  string  $facility  Human-readable form label, e.g.
     *                            "Performance Check Gedung Tower".
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
                'activity' => "[Auto] {$facility} oleh {$creator->name}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('TfpActivityLogger: appendLogbookNote failed', [
                'facility' => $facility, 'date' => $date, 'shift' => $shift, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch the TFP logbook for the given date or auto-create it. Returns
     * null if even creation fails (e.g. concurrent insert lost both ways —
     * extremely unlikely but should be silent rather than 500).
     */
    private function ensureLogbook(string $date, LocalUser $creator): ?LogbookTfp
    {
        $existing = LogbookTfp::whereDate('date', $date)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return $this->logbookService->createLogbook($date, $creator);
        } catch (\Throwable) {
            // Race condition: another request might have just created it.
            return LogbookTfp::whereDate('date', $date)->first();
        }
    }
}
