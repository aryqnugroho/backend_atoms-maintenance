<?php

namespace App\Models\Logbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LogbookTfpNote — catatan/aktivitas timeline per shift dalam logbook harian.
 */
class LogbookTfpNote extends Model
{
    protected $table = 'logbook_tfp_notes';

    protected $fillable = [
        'logbook_tfp_id',
        'shift',
        'time',
        'activity',
    ];

    public const VALID_SHIFTS = ['pagi', 'siang', 'malam'];

    public function logbook(): BelongsTo
    {
        return $this->belongsTo(LogbookTfp::class, 'logbook_tfp_id');
    }
}
