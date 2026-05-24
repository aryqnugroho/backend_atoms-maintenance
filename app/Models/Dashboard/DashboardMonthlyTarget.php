<?php

namespace App\Models\Dashboard;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DashboardMonthlyTarget — one row per module that should be checked
 * at least N times per calendar month. Read by DashboardMonthlySummaryService.
 */
class DashboardMonthlyTarget extends Model
{
    protected $table = 'dashboard_monthly_targets';

    protected $fillable = [
        'module_key',
        'min_count',
        'sort_order',
        'is_active',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'min_count'  => 'integer',
        'sort_order' => 'integer',
    ];

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
}
