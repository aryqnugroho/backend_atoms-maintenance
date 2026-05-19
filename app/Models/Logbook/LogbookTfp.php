<?php

namespace App\Models\Logbook;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LogbookTfp — header logbook harian TFP.
 * Satu record per tanggal (unique constraint on `date`).
 *
 * Menggunakan hard delete (bukan soft delete) agar unique constraint `date`
 * benar-benar dibebaskan saat logbook dihapus, sehingga tanggal yang sama
 * bisa dipakai kembali.
 */
class LogbookTfp extends Model
{

    protected $table = 'logbook_tfps';

    protected $fillable = [
        'date',
        'manager_signed_by_id',
        'manager_signed_by_name',
        'manager_signed_by_role',
        'manager_signature',
        'manager_signed_at',
        'created_by_id',
        'created_by_name',
    ];

    protected $casts = [
        'date'              => 'date:Y-m-d',
        'manager_signed_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'manager_signed_by_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LogbookTfpItem::class, 'logbook_tfp_id')
            ->with('equipment');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LogbookTfpNote::class, 'logbook_tfp_id')
            ->orderBy('shift')
            ->orderBy('time');
    }

    // ─── Helpers ───────────────────────────────────────────────

    public function isSigned(): bool
    {
        return !empty($this->manager_signature);
    }

    public function scopeByYear($q, int $year)
    {
        return $q->whereYear('date', $year);
    }
}
