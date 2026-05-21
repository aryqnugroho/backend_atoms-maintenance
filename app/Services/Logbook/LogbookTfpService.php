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
 *
 * Signature: 3 slot terpisah per shift (pagi/siang/malam). Manager Teknik yang
 * bertugas pada shift tertentu hanya bisa menandatangani slot shift-nya.
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
            ->with(['creator:id,name'])
            ->withCount('notes');

        if (!empty($filters['year'])) {
            $query->byYear((int) $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->whereMonth('date', (int) $filters['month']);
        }

        if (!empty($filters['signed'])) {
            // signed=yes → all 3 shifts signed; signed=no → no shift signed
            if ($filters['signed'] === 'yes') {
                $query->whereNotNull('manager_signature_pagi')
                    ->whereNotNull('manager_signature_siang')
                    ->whereNotNull('manager_signature_malam');
            } elseif ($filters['signed'] === 'no') {
                $query->whereNull('manager_signature_pagi')
                    ->whereNull('manager_signature_siang')
                    ->whereNull('manager_signature_malam');
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
            'creator:id,name',
        ])->find($id);
    }

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

    public function createLogbook(string $date, ?LocalUser $creator = null): LogbookTfp
    {
        if (LogbookTfp::whereDate('date', $date)->exists()) {
            throw new RuntimeException("Logbook TFP untuk tanggal {$date} sudah ada.");
        }

        try {
            return DB::transaction(function () use ($date, $creator) {
                $logbook = LogbookTfp::create([
                    'date'            => $date,
                    'created_by_id'   => $creator?->id,
                    'created_by_name' => $creator?->name,
                ]);

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

                return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw new RuntimeException("Logbook TFP untuk tanggal {$date} sudah ada.");
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique')) {
                throw new RuntimeException("Logbook TFP untuk tanggal {$date} sudah ada.");
            }
            throw $e;
        }
    }

    // ─── Personnel On Duty ─────────────────────────────────────

    public function getManagersOnDutyForDates(array $dates): array
    {
        return $this->rosteringService->getShiftManagersForDates($dates);
    }

    public function getPersonnelOnDuty(string $date): array
    {
        $shifts = ['pagi', 'siang', 'malam'];
        $result = [];

        foreach ($shifts as $shift) {
            try {
                $context = $this->rosteringService->getShiftContext($shift, $date);

                $manager = $context['manager'] ?? null;
                $supervisorTfp = $context['supervisor_tfp'] ?? null;

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

    // ─── Equipment Management ──────────────────────────────────

    public function addEquipmentToLogbook(LogbookTfp $logbook, string $name, string $category): LogbookTfp
    {
        $equipment = TfpEquipment::firstOrCreate(
            ['name' => $name, 'category' => $category],
            ['is_active' => true, 'order' => 999]
        );

        if ($logbook->items()->where('tfp_equipment_id', $equipment->id)->exists()) {
            throw new RuntimeException("Peralatan '{$name}' sudah ada di logbook ini.");
        }

        $logbook->items()->create([
            'tfp_equipment_id' => $equipment->id,
            'status_pagi'      => null,
            'status_siang'     => null,
            'status_malam'     => null,
        ]);

        return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
    }

    public function editEquipmentInLogbook(LogbookTfp $logbook, int $itemId, array $data): LogbookTfp
    {
        $item = $logbook->items()->where('id', $itemId)->first();
        if (!$item) {
            throw new RuntimeException('Item peralatan tidak ditemukan.');
        }

        $equipment = $item->equipment;
        if (!$equipment) {
            throw new RuntimeException('Data peralatan tidak ditemukan.');
        }

        if (isset($data['name'])) {
            $equipment->name = $data['name'];
        }
        if (isset($data['category'])) {
            $equipment->category = $data['category'];
        }
        $equipment->save();

        return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
    }

    public function removeEquipmentFromLogbook(LogbookTfp $logbook, int $itemId): LogbookTfp
    {
        $item = $logbook->items()->where('id', $itemId)->first();
        if (!$item) {
            throw new RuntimeException('Item peralatan tidak ditemukan.');
        }

        $item->delete();

        return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
    }

    // ─── Update Items ──────────────────────────────────────────

    public function updateItems(LogbookTfp $logbook, array $items): LogbookTfp
    {
        return DB::transaction(function () use ($logbook, $items) {
            $existing = $logbook->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                $item = $existing->get($payload['id'] ?? 0);
                if (!$item) continue;

                // Only assign keys that are actually present in the payload, so
                // partial updates (e.g. only `status_pagi`) don't wipe the other shifts.
                $fields = [];
                foreach (['status_pagi', 'status_siang', 'status_malam'] as $k) {
                    if (array_key_exists($k, $payload)) {
                        $fields[$k] = $payload[$k];
                    }
                }
                if (!empty($fields)) {
                    $item->fill($fields);
                    $item->save();
                }
            }

            return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
        });
    }

    /**
     * Bulk-set status for all items of one shift (used by "Mark all S" / "Mark all U/S" / "Reset").
     * Skips items that already have a non-null value when $overwrite=false.
     */
    public function bulkSetShiftStatus(LogbookTfp $logbook, string $shift, ?string $status, bool $overwrite = true): LogbookTfp
    {
        $col = "status_{$shift}";
        return DB::transaction(function () use ($logbook, $col, $status, $overwrite) {
            $query = $logbook->items();
            if (!$overwrite) {
                $query->whereNull($col);
            }
            $query->update([$col => $status, 'updated_at' => now()]);
            return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
        });
    }

    // ─── Notes ─────────────────────────────────────────────────

    public function addNote(
        LogbookTfp $logbook,
        string $shift,
        ?string $time,
        string $activity,
        ?LocalUser $reporter = null,
    ): LogbookTfp {
        $activityWithReporter = $activity;
        if ($reporter) {
            $activityWithReporter = $activity . ' (oleh: ' . $reporter->name . ')';
        }

        $logbook->notes()->create([
            'shift'    => $shift,
            'time'     => $time,
            'activity' => $activityWithReporter,
        ]);

        return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
    }

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
     * Sign the logbook for ONE shift as that shift's Manager Teknik.
     * The signer must be the manager assigned to that shift in the roster.
     */
    public function signLogbook(
        LogbookTfp $logbook,
        string $shift,
        string $base64Signature,
        LocalUser $signer,
    ): LogbookTfp {
        if (!in_array($shift, ['pagi', 'siang', 'malam'], true)) {
            throw new InvalidArgumentException("Shift '{$shift}' tidak valid.");
        }

        if ($logbook->isShiftSigned($shift)) {
            throw new RuntimeException("Tanda tangan untuk shift {$shift} sudah ada dan tidak dapat diubah.");
        }

        // Authorize role (Manager Teknik)
        SignatureAuthorizationService::authorize($signer, 'manager', null, null);

        // Verify signer is the manager assigned to this shift
        $rosterManager = $this->rosteringService->getShiftManager($shift, $logbook->date->format('Y-m-d'));
        if (!$rosterManager) {
            throw new SignerNotAuthorizedException(
                "Tidak ada Manager Teknik yang ditugaskan untuk shift {$shift} pada tanggal " . $logbook->date->format('Y-m-d') . '.'
            );
        }
        if (!WorkOrderService::namesMatch($rosterManager->name, $signer->name)) {
            throw new SignerNotAuthorizedException(
                "Slot tanda tangan shift {$shift} hanya dapat ditandatangani oleh {$rosterManager->name}."
            );
        }

        $this->validateBase64PngSignature($base64Signature);

        $logbook->{"manager_signature_{$shift}"}       = $base64Signature;
        $logbook->{"manager_signed_by_id_{$shift}"}    = $signer->id;
        $logbook->{"manager_signed_by_name_{$shift}"}  = $signer->name;
        $logbook->{"manager_signed_by_role_{$shift}"}  = $signer->role;
        $logbook->{"manager_signed_at_{$shift}"}       = now();
        $logbook->save();

        return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
    }

    // ─── Delete ────────────────────────────────────────────────

    /**
     * Hard-delete a logbook so the date is released and can be re-created on the same day.
     * Allowed even after signing — caller must show a confirm dialog warning the user
     * that signed data will be lost.
     */
    public function deleteLogbook(LogbookTfp $logbook): void
    {
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
