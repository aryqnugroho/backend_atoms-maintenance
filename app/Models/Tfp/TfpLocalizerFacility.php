<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpLocalizerFacility extends Model
{
    protected $table = 'tfp_localizer_facilities';
    protected $fillable = ['localizer_record_id', 'facility_name', 'kondisi', 'keterangan', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    public function record(): BelongsTo { return $this->belongsTo(TfpLocalizerRecord::class, 'localizer_record_id'); }
}
