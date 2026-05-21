<?php

namespace App\Http\Requests\GroundCheck;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroundCheckVhfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_location' => ['nullable', 'string', 'max:255'],
            'equipment_function' => ['nullable', 'string', 'max:1000'],
            'technical_data'     => ['nullable', 'string', 'max:1000'],
            'last_calibration'   => ['nullable', 'string', 'max:1000'],
            'time_filled'        => ['nullable', 'string', 'max:10'],

            // Form 1 — Pengujian Berkala
            'items'              => ['nullable', 'array'],
            'items.*.id'         => ['required_with:items', 'integer'],
            'items.*.calibration_result'   => ['nullable', 'string', 'max:255'],
            'items.*.tolerance'            => ['nullable', 'string', 'max:255'],
            'items.*.tx1_hasil_pd'         => ['nullable', 'string', 'max:255'],
            'items.*.tx1_in_tolerance'     => ['nullable', 'string', 'max:10'],
            'items.*.tx1_out_of_tolerance' => ['nullable', 'string', 'max:10'],
            'items.*.tx2_hasil_pd'         => ['nullable', 'string', 'max:255'],
            'items.*.tx2_in_tolerance'     => ['nullable', 'string', 'max:10'],
            'items.*.tx2_out_of_tolerance' => ['nullable', 'string', 'max:10'],
            'items.*.keterangan'           => ['nullable', 'string', 'max:1000'],

            // Form 2 — Pelaksanaan Kegiatan Pemeliharaan Pencegahan
            'maintenance_items'                  => ['nullable', 'array'],
            'maintenance_items.*.id'             => ['required_with:maintenance_items', 'integer'],
            'maintenance_items.*.toleransi'      => ['nullable', 'string', 'max:255'],
            'maintenance_items.*.interface_value' => ['nullable', 'string', 'max:255'],
            'maintenance_items.*.tx1_value'      => ['nullable', 'string', 'max:255'],
            'maintenance_items.*.tx2_value'      => ['nullable', 'string', 'max:255'],
            'maintenance_items.*.keterangan'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
