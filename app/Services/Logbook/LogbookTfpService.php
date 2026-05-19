<?php

namespace App\Services\Logbook;

use App\Exceptions\SignerNotAuthorizedException;
use App\Models\LocalUser;
use App\Models\Logbook\LogbookTfp;
use App\Models\Logbook\LogbookTfpItem;
use App\Models\Logbook\TfpEquipment;
use App\Services\RosteringIntegrationService;
use App\Services\SignatureAuthorizationService;
use App\Services\WorkOrderService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * LogbookTfpService — orchestrates the daily TFP Logbook.
 *
 * One logbook per calendar date (unique constraint).
 * Personnel on duty is resolved from rostering for all 3 shifts of that date.
 * Manager Teknik signs the logbook (role-based delegation applies).
 */
class LogbookTfpService
{
    public function __construct(
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listLogbooks(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = LogbookTfp::query()
            ->with(['manager:id,name', 'creator:id,name'])
            ->withCount('notes');

        if (!empty($filters['year'])) {
            $query->byYear((int) $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->whereMonth('date', (int) $filters['month']);
        }

        if (!empty($filters['signed'])) {
            if ($filters['signed'] === 'yes') {
                $query->whereNotNull('manager_signature');
            } elseif ($filters['signed'] === 'no') {
                $query->whereNull('manager_signature');
            }
        }

        return $query
            ->orderByDesc('date')
            ->paginate($perPage);
    }

    public function findLogbook(int $id): ?LogbookTfp
    {
        return LogbookTfp::with([
            'items.equipment',
            'notes',
            'manager:id,name',
            'creator:id,name',
        ])->find($id);
    }

    /**
     * Get available years from logbook dates.
     */
    public function getAvailableYears(): array
    {
        $years = LogbookTfp::selectRaw('EXTRACT(YEAR FROM date)::int AS y')
            ->whereNotNull('date')
            ->groupBy('y')
            ->orderByDesc('y')
            ->pluck('y')
            ->values()
            ->toArray();

        $currentYear = (int) now()->format('Y');
        if (!in_array($currentYear, $years, true)) {
            array_unshift($years, $currentYear);
        }

        return $years;
    }

    // ─── Create ────────────────────────────────────────────────

    /**
     * Create a new daily logbook for the given date.
     * Auto-seeds items from active TfpEquipment master data.
     *
     * @throws RuntimeException if a logbook for this date already exists.
     */
    public function createLogbook(string $date, ?LocalUser $creator = null): LogbookTfp
    {
        // Check uniqueness (fast path — avoids hitting the DB constraint)
        if (LogbookTfp::whereDate('date', $date)->exists()) {
            throw new RuntimeException(
                "Logbook TFP untuk tanggal {$date} sudah ada."
            );
        }

        try {
            return DB::transaction(function () use ($date, $creator) {
                $logbook = LogbookTfp::create([
                    'date'            => $date,
                    'created_by_id'   => $creator?->id,
                    'created_by_name' => $creator?->name,
                ]);

                // Seed items from active equipment master
                $equipments = TfpEquipment::active()->ordered()->get();
                $itemRows = $equipments->map(fn ($eq) => [
                    'logbook_tfp_id'   => $logbook->id,
                    'tfp_equipment_id' => $eq->id,
                    'status_pagi'      => null,
                    'status_siang'     => null,
                    'status_malam'     => null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ])->toArray();

                if (!empty($itemRows)) {
                    LogbookTfpItem::insert($itemRows);
                }

                return $logbook->fresh([
                    'items.equipment',
                    'notes',
                    'manager:id,name',
                    'creator:id,name',
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race condition: another request inserted the same date between our check and insert
            throw new RuntimeException(
                "Logbook TFP untuk tanggal {$date} sudah ada."
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Catch generic unique violation (SQLSTATE 23505) for older Laravel versions
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique')) {
                throw new RuntimeException(
                    "Logbook TFP untuk tanggal {$date} sudah ada."
                );
            }
            throw $e;
        }
    }

    // ─── Personnel On Duty ─────────────────────────────────────

    /**
     * Resolve personnel on duty for all 3 shifts of the logbook date.
     * Pulled from rostering (read-only). Returns null values gracefully.
     */
    public function getPersonnelOnDuty(string $date): array
    {
        $shifts = ['pagi', 'siang', 'malam'];
        $result = [];

        foreach ($shifts as $shift) {
            try {
                $context = $this->rosteringService->getShiftContext($shift, $date);

                $manager = $context['manager'] ?? null;
                $supervisorTfp = $context['supervisor_tfp'] ?? null;

                // TFP technicians: employee_type = 'Support'
                $personnel = collect($context['personnel'] ?? [])
                    ->filter(fn ($p) => ($p->employee_type ?? '') === 'Support')
                    ->values();

                $result[$shift] = [
                    'roster_available' => $context['roster_available'] ?? false,
                    'manager'          => $manager ? ['name' => $manager->name, 'user_id' => $manager->user_id] : null,
                    'supervisor'       => $supervisorTfp ? ['name' => $supervisorTfp->name, 'user_id' => $supervisorTfp->user_id] : null,
                    'technicians'      => $personnel->map(fn ($p) => [
                        'name'    => $p->name,
                        'user_id' => $p->user_id,
                    ])->toArray(),
                ];
            } catch (\Throwable) {
                $result[$shift] = [
                    'roster_available' => false,
                    'manager'          => null,
                    'supervisor'       => null,
                    'technicians'      => [],
                ];
            }
        }

        return $result;
    }

    // ─── Update Items ──────────────────────────────────────────

    /**
     * Update S/US status for equipment items.
     */
    public function updateItems(LogbookTfp $logbook, array $items): LogbookTfp
    {
        return DB::transaction(function () use ($logbook, $items) {
            $existing = $logbook->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                $item = $existing->get($payload['id'] ?? 0);
                if (!$item) continue;

                $item->fill([
                    'status_pagi'  => $payload['status_pagi'] ?? null,
                    'status_siang' => $payload['status_siang'] ?? null,
                    'status_malam' => $payload['status_malam'] ?? null,
                ]);
                $item->save();
            }

            return $logbook->fresh(['items.equipment', 'notes', 'manager:id,name', 'creator:id,name']);
        });
    }

    // ─── Notes ─────────────────────────────────────────────────

    /**
     * Add a timeline note to the logbook.
     * The activity text is stored as-is; the caller (controller) passes the user
     * so the service can append the reporter name.
     */
    public function addNote(
        LogbookTfp $logbook,
        string $shift,
        ?string $time,
        string $activity,
        ?LocalUser $reporter = null,
    ): LogbookTfp {
        // Append reporter name to activity for audit trail
        $activityWithReporter = $activity;
        if ($reporter) {
            $activityWithReporter = $activity . ' (oleh: ' . $reporter->name . ')';
        }

        $logbook->notes()->create([
            'shift'    => $shift,
            'time'     => $time,
            'activity' => $activityWithReporter,
        ]);

        return $logbook->fresh(['items.equipment', 'notes', 'manager:id,name', 'creator:id,name']);
    }

    /**
     * Delete a note from the logbook.
     */
    public function deleteNote(LogbookTfp $logbook, int $noteId): void
    {
        $note = $logbook->notes()->where('id', $noteId)->first();
        if (!$note) {
            throw new RuntimeException('Catatan tidak ditemukan.');
        }
        $note->delete();
    }

    // ─── Sign ──────────────────────────────────────────────────

    /**
     * Sign the logbook as Manager Teknik.
     * Uses role-based delegation (Manager can sign own slot only).
     */
    public function signLogbook(
        LogbookTfp $logbook,
        string $base64Signature,
        LocalUser $signer,
    ): LogbookTfp {
        if (!empty($logbook->manager_signature)) {
            throw new RuntimeException('Logbook sudah ditandatangani dan tidak dapat diubah.');
        }

        // Manager slot: only Manager Teknik, own slot (no target name constraint for logbook)
        SignatureAuthorizationService::authorize($signer, 'manager', null, null);

        $this->validateBase64PngSignature($base64Signature);

        $logbook->manager_signature    = $base64Signature;
        $logbook->manager_signed_by_id = $signer->id;
        $logbook->manager_signed_by_name = $signer->name;
        $logbook->manager_signed_by_role = $signer->role;
        $logbook->manager_signed_at    = now();
        $logbook->save();

        return $logbook->fresh([
            'items.equipment',
            'notes',
            'manager:id,name',
            'creator:id,name',
        ]);
    }

    // ─── Delete ────────────────────────────────────────────────

    public function deleteLogbook(LogbookTfp $logbook): void
    {
        if (!empty($logbook->manager_signature)) {
            throw new RuntimeException('Logbook yang sudah ditandatangani tidak dapat dihapus.');
        }
        // Hard delete — removes the row completely so the date can be reused
        $logbook->delete();
    }

    // ─── Private ───────────────────────────────────────────────

    private function validateBase64PngSignature(string $base64): void
    {
        $prefix = 'data:image/png;base64,';
        if (!str_starts_with($base64, $prefix)) {
            throw new InvalidArgumentException('Signature must be a base64 PNG data URL.');
        }
        $payload = substr($base64, strlen($prefix));
        if ($payload === '' || base64_decode($payload, true) === false) {
            throw new InvalidArgumentException('Signature payload is not valid base64.');
        }
    }
}
