<?php

namespace App\Models\Logbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CnsdEquipment — master data peralatan CNSD untuk logbook.
 * Seeded via CnsdEquipmentSeeder dari form fisik LOG BOOK CNS & AUTOMATION.
 *
 * `is_measurement = true` untuk item yang menggunakan input nilai numerik
 * (mis. SUHU RUANGAN dengan satuan °C) alih-alih toggle S/US.
 */
class CnsdEquipment extends Model
{
    protected $table = 'cnsd_equipments';

    protected $fillable = [
        'category',
        'name',
        'is_measurement',
        'unit',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_measurement' => 'boolean',
        'is_active'      => 'boolean',
        'order'          => 'integer',
    ];

    public function logbookItems(): HasMany
    {
        return $this->hasMany(LogbookCnsdItem::class, 'cnsd_equipment_id');
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
