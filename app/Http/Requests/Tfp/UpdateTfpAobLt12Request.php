<?php

namespace App\Http\Requests\Tfp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Updating a TFP AOB Lantai 1 & 2 record allows mutating item measurement values
 * and facility condition values. Personnel, date, shift, signatures, and
 * form_number are never editable here.
 */
class UpdateTfpAobLt12Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Items (measurement parameters)
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.id'                     => ['required', 'integer'],
            'items.*.panel_a05_app_room'     => ['nullable', 'string', 'max:100'],
            'items.*.panel_a06_app_room'     => ['nullable', 'string', 'max:100'],
            'items.*.panel_a07_app_room'     => ['nullable', 'string', 'max:100'],
            'items.*.panel_a08_gudang_lt1'   => ['nullable', 'string', 'max:100'],
            'items.*.panel_a22_gudang_lt1'   => ['nullable', 'string', 'max:100'],
            'items.*.panel_a09_amsc_room'    => ['nullable', 'string', 'max:100'],

            // Facilities (optional)
            'facilities'              => ['sometimes', 'array'],
            'facilities.*.id'         => ['required_with:facilities', 'integer'],
            'facilities.*.kondisi'    => ['nullable', 'string', 'in:Baik,Normal,Tidak Baik'],
            'facilities.*.keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'      => 'Minimal satu item harus disertakan.',
            'items.array'         => 'Items harus berupa array.',
            'items.*.id.required' => 'Setiap item wajib menyertakan id.',
            'facilities.*.kondisi.in' => 'Kondisi harus salah satu dari: Baik, Normal, Tidak Baik.',
        ];
    }
}
