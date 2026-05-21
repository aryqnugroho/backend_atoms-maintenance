<?php

namespace App\Http\Requests\GroundCheck;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroundCheckLlzRequest extends FormRequest
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

            // Form 2 metadata
            'curve_facility'     => ['nullable', 'string', 'max:255'],
            'curve_merk'         => ['nullable', 'string', 'max:255'],
            'curve_ident_freq'   => ['nullable', 'string', 'max:255'],
            'curve_jarak_ant'    => ['nullable', 'string', 'max:50'],

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

            // Form 2 — Performance Curve points
            'curve_points'                          => ['nullable', 'array'],
            'curve_points.*.id'                     => ['required_with:curve_points', 'integer'],
            'curve_points.*.tx1_ddm_pct'            => ['nullable', 'numeric'],
            'curve_points.*.tx1_ddm_ua'             => ['nullable', 'numeric'],
            'curve_points.*.tx1_sum_pct'            => ['nullable', 'numeric'],
            'curve_points.*.tx1_mod_90hz'           => ['nullable', 'numeric'],
            'curve_points.*.tx1_mod_150hz'          => ['nullable', 'numeric'],
            'curve_points.*.tx1_rf_level_db'        => ['nullable', 'numeric'],
            'curve_points.*.tx2_ddm_pct'            => ['nullable', 'numeric'],
            'curve_points.*.tx2_ddm_ua'             => ['nullable', 'numeric'],
            'curve_points.*.tx2_sum_pct'            => ['nullable', 'numeric'],
            'curve_points.*.tx2_mod_90hz'           => ['nullable', 'numeric'],
            'curve_points.*.tx2_mod_150hz'          => ['nullable', 'numeric'],
            'curve_points.*.tx2_rf_level_db'        => ['nullable', 'numeric'],
        ];
    }
}
