<?php

namespace App\Services\Logbook;

use App\Exceptions\SignerNotAuthorizedException;
use App\Models\LocalUser;
use App\Models\Logbook\CnsdEquipment;
use App\Models\Logbook\LogbookCnsd;
use App\Models\Logbook\LogbookCnsdItem;
use App\Services\RosteringIntegrationService;
use App\Services\SignatureAuthorizationService;
use App\Services\WorkOrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * LogbookCnsdService — daily CNSD logbook with per-shift signatures.
 * Mirror of LogbookTfpService.
 */
class LogbookCnsdService
{
    public function __construct(
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listLogbooks(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = LogbookCnsd::query()
            ->with(['creator:id,name'])
            ->withCount('notes');

        if (!empty($filters['year'])) {
            $query->byYear((int) $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->whereMonth('date', (int) $filters['month']);
        }
        if (!empty($filters['signed'])) {
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

        return $query->orderByDesc('date')->paginate($perPage);
    }

    public function findLogbook(int $id): ?LogbookCnsd
    {
        return LogbookCnsd::with(['items.equipment', 'notes', 'creator:id,name'])->find($id);
    }

    public function getAvailableYears(): array
    {
        $years = LogbookCnsd::selectRaw('EXTRACT(YEAR FROM date)::int AS y')
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

    public function createLogbook(string $date, ?LocalUser $creator = null): LogbookCnsd
    {
        if (LogbookCnsd::whereDate('date', $date)->exists()) {
            throw new RuntimeException("Logbook CNSD untuk tanggal {$date} sudah ada.");
        }

        try {
            return DB::transaction(function () use ($date, $creator) {
                $logbook = LogbookCnsd::create([
                    'date'            => $date,
                    'created_by_id'   => $creator?->id,
                    'created_by_name' => $creator?->name,
                ]);

                $equipments = CnsdEquipment::active()->ordered()->get();
                $itemRows = $equipments->map(fn ($eq) => [
                    'logbook_cnsd_id'   => $logbook->id,
                    'cnsd_equipment_id' => $eq->id,
                    'status_pagi'       => null,
                    'status_siang'      => null,
                    'status_malam'      => null,
                    'value_pagi'        => null,
                    'value_siang'       => null,
                    'value_malam'       => null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ])->toArray();

                if (!empty($itemRows)) {
                    LogbookCnsdItem::insert($itemRows);
                }

                return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw new RuntimeException("Logbook CNSD untuk tanggal {$date} sudah ada.");
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique')) {
                throw new RuntimeException("Logbook CNSD untuk tanggal {$date} sudah ada.");
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

                $manager        = $context['manager'] ?? null;
                $supervisorCnsd = $context['supervisor_cnsd'] ?? null;

                $personnel = collect($context['personnel'] ?? [])
                    ->filter(fn ($p) => ($p->employee_type ?? '') === 'CNS')
                    ->values();

                $result[$shift] = [
                    'roster_available' => $context['roster_available'] ?? false,
                    'manager'          => $manager ? ['name' => $manager->name, 'user_id' => $manager->user_id] : null,
                    'supervisor'       => $supervisorCnsd ? ['name' => $supervisorCnsd->name, 'user_id' => $supervisorCnsd->user_id] : null,
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

    public function addEquipmentToLogbook(LogbookCnsd $logbook, string $name, string $category): LogbookCnsd
    {
        $equipment = CnsdEquipment::firstOrCreate(
            ['name' => $name, 'category' => $category],
            ['is_active' => true, 'order' => 9999, 'is_measurement' => false]
        );

        if ($logbook->items()->where('cnsd_equipment_id', $equipment->id)->exists()) {
            throw new RuntimeException("Peralatan '{$name}' sudah ada di logbook ini.");
        }

        $logbook->items()->create([
            'cnsd_equipment_id' => $equipment->id,
            'status_pagi'       => null,
            'status_siang'      => null,
            'status_malam'      => null,
        ]);

        return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
    }

    public function editEquipmentInLogbook(LogbookCnsd $logbook, int $itemId, array $data): LogbookCnsd
    {
        $item = $logbook->items()->where('id', $itemId)->first();
        if (!$item) throw new RuntimeException('Item peralatan tidak ditemukan.');
        $equipment = $item->equipment;
        if (!$equipment) throw new RuntimeException('Data peralatan tidak ditemukan.');

        if (isset($data['name'])) $equipment->name = $data['name'];
        if (isset($data['category'])) $equipment->category = $data['category'];
        $equipment->save();

        return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
    }

    public function removeEquipmentFromLogbook(LogbookCnsd $logbook, int $itemId): LogbookCnsd
    {
        $item = $logbook->items()->where('id', $itemId)->first();
        if (!$item) throw new RuntimeException('Item peralatan tidak ditemukan.');
        $item->delete();
        return $logbook->fresh(['items.equipment', 'notes', 'creator:id,name']);
    }

    // ─── Update Items ──────────────────────────────────────────

    public function updateItems(LogbookCnsd $logbook, array $items): LogbookCnsd
    {
        return DB::transaction(function () use ($logbook, $items) {
            $existing = $logbook->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                $item = $existing->get($payload['id'] ?? 0);
                if (!$item) continue;

                $fields = [];
                foreach (['status_pagi', 'status_siang', 'status_malam'] as $k) {
                    if (array_key_exists($k, $payload)) {
                        $fields[$k] = $payload[$k];
                    }
                }
                foreach (['value_pagi', 'value_siang', 'value_malam'] as $k) {
                    if (array_key_exists($k, $payload)) {
                        $v = $payload[$k];
                        $fields[$k] = $v === null ? null : mb_substr((string) $v, 0, 30);
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
     * Bulk-set status for all items of one shift.
     */
    public function bulkSetShiftStatus(LogbookCnsd $logbook, string $shift, ?string $status, bool $overwrite = true): LogbookCnsd
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
        LogbookCnsd $logbook,
        string $shift,
        ?string $time,
        string $activity,
        ?LocalUser $reporter = null,
    ): LogbookCnsd {
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

    public function deleteNote(LogbookCnsd $logbook, int $noteId): void
    {
        $note = $logbook->notes()->where('id', $noteId)->first();
        if (!$note) throw new RuntimeException('Catatan tidak ditemukan.');
        $note->delete();
    }

    // ─── Sign ──────────────────────────────────────────────────

    public function signLogbook(
        LogbookCnsd $logbook,
        string $shift,
        string $base64Signature,
        LocalUser $signer,
    ): LogbookCnsd {
        if (!in_array($shift, ['pagi', 'siang', 'malam'], true)) {
            throw new InvalidArgumentException("Shift '{$shift}' tidak valid.");
        }

        if ($logbook->isShiftSigned($shift)) {
            throw new RuntimeException("Tanda tangan untuk shift {$shift} sudah ada dan tidak dapat diubah.");
        }

        SignatureAuthorizationService::authorize($signer, 'manager', null, null);

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
     * Hard-delete a logbook. Allowed even after signing — caller must confirm.
     */
    public function deleteLogbook(LogbookCnsd $logbook): void
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
