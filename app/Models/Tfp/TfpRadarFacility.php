<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpRadarFacility extends Model
{
    protected $table = 'tfp_radar_facilities';

    protected $fillable = ['radar_record_id', 'facility_name', 'kondisi', 'keterangan', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function record(): BelongsTo { return $this->belongsTo(TfpRadarRecord::class, 'radar_record_id'); }
}
