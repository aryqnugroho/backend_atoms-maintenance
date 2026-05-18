<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpDvorFacility extends Model
{
    protected $table = 'tfp_dvor_facilities';
    protected $fillable = ['dvor_record_id', 'facility_name', 'kondisi', 'keterangan', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    public function record(): BelongsTo { return $this->belongsTo(TfpDvorRecord::class, 'dvor_record_id'); }
}
