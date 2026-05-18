<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpGlidepathFacility extends Model
{
    protected $table = 'tfp_glidepath_facilities';
    protected $fillable = ['glidepath_record_id', 'facility_name', 'kondisi', 'keterangan', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    public function record(): BelongsTo { return $this->belongsTo(TfpGlidepathRecord::class, 'glidepath_record_id'); }
}
