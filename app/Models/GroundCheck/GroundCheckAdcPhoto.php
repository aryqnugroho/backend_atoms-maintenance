<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * GroundCheckAdcPhoto — lampiran foto dokumentasi Ground Check ADC.
 *
 * File disimpan di disk `public` dibawah path
 * `ground-check/adc/{record_id}/...`. URL publik tersedia via accessor
 * `url` (auto-included pada serialization).
 */
class GroundCheckAdcPhoto extends Model
{
    protected $table = 'ground_check_adc_photos';

    protected $fillable = [
        'ground_check_adc_record_id',
        'path',
        'original_name',
        'caption',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
        'uploaded_by_name',
        'sort_order',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = ['url'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundCheckAdcRecord::class, 'ground_check_adc_record_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (empty($this->path)) {
            return null;
        }
        return Storage::disk('public')->url($this->path);
    }
}
