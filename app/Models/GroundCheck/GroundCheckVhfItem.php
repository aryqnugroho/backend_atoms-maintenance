<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckVhfItem extends Model
{
    protected $table = 'ground_check_vhf_items';

    protected $fillable = [
        'ground_check_vhf_record_id',
        'section_name',
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
        'sort_order',
    ];

    protected $casts = [
        'is_header' => 'boolean',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundCheckVhfRecord::class, 'ground_check_vhf_record_id');
    }
}
