<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpAobGroundFacility — one facility condition row for a TFP AOB Ground record.
 *
 * Seeded from TfpAobGroundTemplate at create-time. Technicians fill in
 * kondisi (Baik / Normal / Tidak Baik) and optional keterangan.
 */
class TfpAobGroundFacility extends Model
{
    protected $table = 'tfp_aob_ground_facilities';

    protected $fillable = [
        'aob_ground_record_id',
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
        return $this->belongsTo(TfpAobGroundRecord::class, 'aob_ground_record_id');
    }
}
