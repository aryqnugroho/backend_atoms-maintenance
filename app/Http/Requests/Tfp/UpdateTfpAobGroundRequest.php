<?php

namespace App\Http\Requests\Tfp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Updating a TFP AOB Ground record allows mutating item measurement values
 * and facility condition values. Personnel, date, shift, signatures, and
 * form_number are never editable here.
 */
class UpdateTfpAobGroundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional explicit time override (HH:MM). Falls back to now() on the server when omitted.
            'time_filled'                    => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],

            // Items (measurement parameters)
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.id'                     => ['required', 'integer'],
            'items.*.panel_cos_a03_input'    => ['nullable', 'string', 'max:100'],
            'items.*.panel_cos_a03_output'   => ['nullable', 'string', 'max:100'],
            'items.*.panel_ats_a12_input'    => ['nullable', 'string', 'max:100'],
            'items.*.panel_ats_a12_output'   => ['nullable', 'string', 'max:100'],
            'items.*.ups_tescom_a_input'     => ['nullable', 'string', 'max:100'],
            'items.*.ups_tescom_a_output'    => ['nullable', 'string', 'max:100'],
            'items.*.ups_tescom_b_input'     => ['nullable', 'string', 'max:100'],
            'items.*.ups_tescom_b_output'    => ['nullable', 'string', 'max:100'],

            // Facilities (optional)
            'facilities'              => ['sometimes', 'array'],
            'facilities.*.id'         => ['required_with:facilities', 'integer'],
            'facilities.*.kondisi'    => ['nullable', 'string', 'in:Baik,Rusak,Tidak Ada'],
            'facilities.*.keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'time_filled.regex'   => 'Waktu pengisian harus dalam format HH:MM.',
            'items.required'      => 'Minimal satu item harus disertakan.',
            'items.array'         => 'Items harus berupa array.',
            'items.*.id.required' => 'Setiap item wajib menyertakan id.',
            'facilities.*.kondisi.in' => 'Kondisi harus salah satu dari: Baik, Rusak, Tidak Ada.',
        ];
    }
}
