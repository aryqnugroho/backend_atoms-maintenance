<?php

namespace App\Models\Logbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TfpEquipment — master data peralatan TFP untuk logbook.
 * Seeded via TfpEquipmentSeeder dari form fisik LOG BOOK FASILITAS PENUNJANG.
 */
class TfpEquipment extends Model
{
    protected $table = 'tfp_equipments';

    protected $fillable = [
        'category',
        'name',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order'     => 'integer',
    ];

    public function logbookItems(): HasMany
    {
        return $this->hasMany(LogbookTfpItem::class, 'tfp_equipment_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('order');
    }
}
