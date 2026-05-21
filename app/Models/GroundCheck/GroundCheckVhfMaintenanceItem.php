<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckVhfMaintenanceItem extends Model
{
    protected $table = 'ground_check_vhf_maintenance_items';

    protected $fillable = [
        'ground_check_vhf_record_id',
        'section_number',
        'section_label',
        'subsection_label',
        'item_code',
        'parameter_name',
        'toleransi',
        'interface_value',
        'tx1_value',
        'tx2_value',
        'keterangan',
        'is_section_header',
        'is_subsection_header',
        'input_type',
        'sort_order',
    ];

    protected $casts = [
        'is_section_header'    => 'boolean',
        'is_subsection_header' => 'boolean',
        'section_number'       => 'integer',
        'sort_order'           => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundCheckVhfRecord::class, 'ground_check_vhf_record_id');
    }
}
