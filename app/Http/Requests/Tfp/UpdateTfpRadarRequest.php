<?php

namespace App\Http\Requests\Tfp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTfpRadarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.id'                     => ['required', 'integer'],
            'items.*.panel_rd01'             => ['nullable', 'string', 'max:100'],
            'items.*.panel_rd02'             => ['nullable', 'string', 'max:100'],
            'items.*.panel_cos_rd03_input'   => ['nullable', 'string', 'max:100'],
            'items.*.panel_cos_rd03_output'  => ['nullable', 'string', 'max:100'],
            'items.*.ups_topaz_input'        => ['nullable', 'string', 'max:100'],
            'items.*.ups_topaz_output'       => ['nullable', 'string', 'max:100'],
            'items.*.panel_rd04'             => ['nullable', 'string', 'max:100'],
            'items.*.panel_rd05'             => ['nullable', 'string', 'max:100'],
            'items.*.panel_rd06'             => ['nullable', 'string', 'max:100'],
            'items.*.panel_rd07'             => ['nullable', 'string', 'max:100'],
            'items.*.panel_rd08'             => ['nullable', 'string', 'max:100'],
            'facilities'                     => ['sometimes', 'array'],
            'facilities.*.id'                => ['required_with:facilities', 'integer'],
            'facilities.*.kondisi'           => ['nullable', 'string', 'in:Baik,Normal,Tidak Baik'],
            'facilities.*.keterangan'        => ['nullable', 'string', 'max:500'],
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
