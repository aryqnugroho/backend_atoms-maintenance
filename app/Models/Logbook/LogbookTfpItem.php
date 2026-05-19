<?php

namespace App\Models\Logbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LogbookTfpItem — status peralatan per shift dalam satu logbook harian.
 * S = Serviceable, US = Unserviceable, null = belum dicek.
 */
class LogbookTfpItem extends Model
{
    protected $table = 'logbook_tfp_items';

    protected $fillable = [
        'logbook_tfp_id',
        'tfp_equipment_id',
        'status_pagi',
        'status_siang',
        'status_malam',
    ];

    public const VALID_STATUSES = ['S', 'US'];

    public function logbook(): BelongsTo
    {
        return $this->belongsTo(LogbookTfp::class, 'logbook_tfp_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(TfpEquipment::class, 'tfp_equipment_id');
    }
}
