<?php

namespace App\Models\Logbook;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LogbookCnsd — header logbook harian CNSD (CNS & Automation).
 * Per-shift signature pattern, mirror of LogbookTfp.
 */
class LogbookCnsd extends Model
{
    protected $table = 'logbook_cnsds';

    protected $fillable = [
        'date',
        'manager_signed_by_id_pagi',  'manager_signed_by_name_pagi',  'manager_signed_by_role_pagi',  'manager_signature_pagi',  'manager_signed_at_pagi',
        'manager_signed_by_id_siang', 'manager_signed_by_name_siang', 'manager_signed_by_role_siang', 'manager_signature_siang', 'manager_signed_at_siang',
        'manager_signed_by_id_malam', 'manager_signed_by_name_malam', 'manager_signed_by_role_malam', 'manager_signature_malam', 'manager_signed_at_malam',
        'created_by_id',
        'created_by_name',
    ];

    protected $casts = [
        'date'                    => 'date:Y-m-d',
        'manager_signed_at_pagi'  => 'datetime',
        'manager_signed_at_siang' => 'datetime',
        'manager_signed_at_malam' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LogbookCnsdItem::class, 'logbook_cnsd_id')->with('equipment');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LogbookCnsdNote::class, 'logbook_cnsd_id')
            ->orderBy('shift')
            ->orderBy('time');
    }

    public function isShiftSigned(string $shift): bool
    {
        $col = "manager_signature_{$shift}";
        return !empty($this->{$col});
    }

    public function isPartiallySigned(): bool
    {
        return $this->isShiftSigned('pagi') || $this->isShiftSigned('siang') || $this->isShiftSigned('malam');
    }

    public function isFullySigned(): bool
    {
        return $this->isShiftSigned('pagi') && $this->isShiftSigned('siang') && $this->isShiftSigned('malam');
    }

    public function scopeByYear($q, int $year)
    {
        return $q->whereYear('date', $year);
    }
}
