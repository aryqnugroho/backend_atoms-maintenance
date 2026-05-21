<?php

namespace App\Http\Requests\GroundCheck;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroundCheckGpRequest extends FormRequest
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

            // Form 2 — NAV analyzer metadata
            'nav_organization'    => ['nullable', 'string', 'max:255'],
            'nav_analyzer_title'  => ['nullable', 'string', 'max:500'],

            // Form 1 — Pengujian Berkala
            'items'              => ['nullable', 'array'],
            'items.*.id'         => ['required_with:items', 'integer'],
            'items.*.calibration_result'   => ['nullable', 'string', 'max:255'],
            'items.*.tolerance'            => ['nullable', 'string', 'max:1000'],
            'items.*.tx1_hasil_pd'         => ['nullable', 'string', 'max:255'],
            'items.*.tx1_in_tolerance'     => ['nullable', 'string', 'max:10'],
            'items.*.tx1_out_of_tolerance' => ['nullable', 'string', 'max:10'],
            'items.*.tx2_hasil_pd'         => ['nullable', 'string', 'max:255'],
            'items.*.tx2_in_tolerance'     => ['nullable', 'string', 'max:10'],
            'items.*.tx2_out_of_tolerance' => ['nullable', 'string', 'max:10'],
            'items.*.keterangan'           => ['nullable', 'string', 'max:1000'],

            // Form 2 — NAV analyzer measurements
            'nav_items'                 => ['nullable', 'array'],
            'nav_items.*.id'            => ['required_with:nav_items', 'integer'],
            'nav_items.*.tx1_value'     => ['nullable', 'string', 'max:255'],
            'nav_items.*.tx2_value'     => ['nullable', 'string', 'max:255'],
            'nav_items.*.keterangan'    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
