<?php

namespace App\Models\Logbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LogbookCnsdNote — catatan/aktivitas timeline per shift dalam logbook harian CNSD.
 */
class LogbookCnsdNote extends Model
{
    protected $table = 'logbook_cnsd_notes';

    protected $fillable = [
        'logbook_cnsd_id',
        'shift',
        'time',
        'activity',
    ];

    public const VALID_SHIFTS = ['pagi', 'siang', 'malam'];

    public function logbook(): BelongsTo
    {
        return $this->belongsTo(LogbookCnsd::class, 'logbook_cnsd_id');
    }
}
