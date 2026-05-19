<?php

namespace App\Models\Logbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LogbookCnsdItem — status (atau nilai numerik) peralatan per shift dalam
 * satu logbook harian CNSD.
 *
 * - status_pagi/siang/malam: 'S' | 'US' | null (untuk item normal)
 * - value_pagi/siang/malam: string nullable (untuk item measurement, mis. °C)
 */
class LogbookCnsdItem extends Model
{
    protected $table = 'logbook_cnsd_items';

    protected $fillable = [
        'logbook_cnsd_id',
        'cnsd_equipment_id',
        'status_pagi',
        'status_siang',
        'status_malam',
        'value_pagi',
        'value_siang',
        'value_malam',
    ];

    public const VALID_STATUSES = ['S', 'US'];

    public function logbook(): BelongsTo
    {
        return $this->belongsTo(LogbookCnsd::class, 'logbook_cnsd_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(CnsdEquipment::class, 'cnsd_equipment_id');
    }
}
