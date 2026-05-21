<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckDvorItem extends Model
{
    protected $table = 'ground_check_dvor_items';

    protected $fillable = [
        'ground_check_dvor_record_id',
        'section_name',
        'subsection_name',
        'item_code',
        'parameter_name',
        'input_type',
        'calibration_result',
        'tolerance',
        'tx1_hasil_pd',
        'tx1_in_tolerance',
        'tx1_out_of_tolerance',
        'tx2_hasil_pd',
        'tx2_in_tolerance',
        'tx2_out_of_tolerance',
        'keterangan',
        'is_header',
        'is_subheader',
        'is_disabled',
        'is_check_only',
        'sort_order',
    ];

    protected $casts = [
        'is_header'      => 'boolean',
        'is_subheader'   => 'boolean',
        'is_disabled'    => 'boolean',
        'is_check_only'  => 'boolean',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundCheckDvorRecord::class, 'ground_check_dvor_record_id');
    }
}
