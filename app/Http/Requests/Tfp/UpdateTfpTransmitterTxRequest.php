<?php

namespace App\Http\Requests\Tfp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTfpTransmitterTxRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                              => ['required', 'array', 'min:1'],
            'items.*.id'                         => ['required', 'integer'],
            'items.*.panel_tx01'                 => ['nullable', 'string', 'max:100'],
            'items.*.panel_tx02'                 => ['nullable', 'string', 'max:100'],
            'items.*.panel_cos_tx03_input'        => ['nullable', 'string', 'max:100'],
            'items.*.panel_cos_tx03_output'       => ['nullable', 'string', 'max:100'],
            'items.*.panel_output_ups_tx04'       => ['nullable', 'string', 'max:100'],
            'items.*.panel_ups_tx07_input'        => ['nullable', 'string', 'max:100'],
            'items.*.panel_ups_tx07_output'       => ['nullable', 'string', 'max:100'],
            'items.*.panel_ac_tx06'               => ['nullable', 'string', 'max:100'],
            'items.*.ups_piller_input'            => ['nullable', 'string', 'max:100'],
            'items.*.ups_piller_output'           => ['nullable', 'string', 'max:100'],
            'items.*.panel_milat_ru11'            => ['nullable', 'string', 'max:100'],
            'facilities'                          => ['sometimes', 'array'],
            'facilities.*.id'                     => ['required_with:facilities', 'integer'],
            'facilities.*.kondisi'                => ['nullable', 'string', 'in:Baik,Normal,Tidak Baik'],
            'facilities.*.keterangan'             => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'          => 'Minimal satu item harus disertakan.',
            'items.*.id.required'     => 'Setiap item wajib menyertakan id.',
            'facilities.*.kondisi.in' => 'Kondisi harus salah satu dari: Baik, Normal, Tidak Baik.',
        ];
    }
}
