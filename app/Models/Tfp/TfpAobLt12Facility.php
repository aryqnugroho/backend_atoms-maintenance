<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpAobLt12Facility — one facility condition row for a TFP AOB Lantai 1 & 2 record.
 *
 * Seeded from TfpAobLt12Template at create-time. Technicians fill in
 * kondisi (Baik / Normal / Tidak Baik) and optional keterangan.
 */
class TfpAobLt12Facility extends Model
{
    protected $table = 'tfp_aob_lt12_facilities';

    protected $fillable = [
        'aob_lt12_record_id',
        'facility_name',
        'kondisi',
        'keterangan',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpAobLt12Record::class, 'aob_lt12_record_id');
    }
}
