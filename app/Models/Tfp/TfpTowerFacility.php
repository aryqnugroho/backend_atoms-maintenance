<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpTowerFacility extends Model
{
    protected $table = 'tfp_tower_facilities';

    protected $fillable = ['tower_record_id', 'facility_name', 'kondisi', 'keterangan', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function record(): BelongsTo { return $this->belongsTo(TfpTowerRecord::class, 'tower_record_id'); }
}
