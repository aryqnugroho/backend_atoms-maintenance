<?php

namespace App\Http\Requests\GroundCheck;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroundCheckDvorRequest extends FormRequest
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
            'identification'     => ['nullable', 'string', 'max:255'],
            'last_calibration'   => ['nullable', 'string', 'max:1000'],
            'time_filled'        => ['nullable', 'string', 'max:10'],

            // Form 1 metadata
            'vor_equipment_name' => ['nullable', 'string', 'max:255'],
            'vor_frequency'      => ['nullable', 'string', 'max:255'],
            'vor_station'        => ['nullable', 'string', 'max:255'],

            // Form 2 metadata
            'curve_organization' => ['nullable', 'string', 'max:500'],

            // Form 4 metadata
            'nav_analyzer_title' => ['nullable', 'string', 'max:500'],
            'note'               => ['nullable', 'string', 'max:2000'],

            // Form 1 — Bearing points
            'bearing_points'                  => ['nullable', 'array'],
            'bearing_points.*.id'             => ['required_with:bearing_points', 'integer'],
            'bearing_points.*.tx1_reading'    => ['nullable', 'numeric'],
            'bearing_points.*.tx1_error'      => ['nullable', 'numeric'],
            'bearing_points.*.tx1_value'      => ['nullable', 'string', 'max:255'],
            'bearing_points.*.tx2_reading'    => ['nullable', 'numeric'],
            'bearing_points.*.tx2_error'      => ['nullable', 'numeric'],
            'bearing_points.*.tx2_value'      => ['nullable', 'string', 'max:255'],

            // Form 3 — Pengujian Berkala
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

            // Form 4 — NAV analyzer measurements
            'nav_items'                 => ['nullable', 'array'],
            'nav_items.*.id'            => ['required_with:nav_items', 'integer'],
            'nav_items.*.ref_tx1_value' => ['nullable', 'string', 'max:255'],
            'nav_items.*.ref_tx2_value' => ['nullable', 'string', 'max:255'],
            'nav_items.*.eq_tx1_value'  => ['nullable', 'string', 'max:255'],
            'nav_items.*.eq_tx2_value'  => ['nullable', 'string', 'max:255'],
        ];
    }
}
