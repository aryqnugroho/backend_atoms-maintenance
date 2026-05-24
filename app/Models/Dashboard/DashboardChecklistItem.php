<?php

namespace App\Models\Dashboard;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DashboardChecklistItem — single row in the editable "Pengingat Pengecekan
 * Harian" catalog. Each row points to a module key (resolved by
 * DashboardModuleRegistry) plus where/when it should appear.
 */
class DashboardChecklistItem extends Model
{
    protected $table = 'dashboard_checklist_items';

    protected $fillable = [
        'module_key',
        'category',
        'shift_type',
        'sort_order',
        'is_active',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public const CATEGORIES = ['wajib', 'shift'];
    public const SHIFT_TYPES = ['pagi', 'siang', 'malam'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'updated_by_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Items relevant for a particular shift: every active wajib item, plus
     * the active shift items pinned to that shift_type. Consumers re-partition
     * into wajib vs shift sections themselves, so this scope only sorts on
     * sort_order (within each group) to keep the order user-controlled.
     */
    public function scopeForShift($q, string $shift)
    {
        return $q->active()
            ->where(function ($qq) use ($shift) {
                $qq->where('category', 'wajib')
                   ->orWhere(function ($qqq) use ($shift) {
                       $qqq->where('category', 'shift')->where('shift_type', $shift);
                   });
            })
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
