<?php

namespace App\Http\Requests\Tfp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTfpTowerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.id'                     => ['required', 'integer'],
            'items.*.panel_a10'              => ['nullable', 'string', 'max:100'],
            'items.*.panel_a11'              => ['nullable', 'string', 'max:100'],
            'items.*.panel_ats_a13_input'    => ['nullable', 'string', 'max:100'],
            'items.*.panel_ats_a13_output'   => ['nullable', 'string', 'max:100'],
            'items.*.panel_a14'              => ['nullable', 'string', 'max:100'],
            'items.*.panel_a16'              => ['nullable', 'string', 'max:100'],
            'items.*.panel_a17'              => ['nullable', 'string', 'max:100'],
            'items.*.panel_a18'              => ['nullable', 'string', 'max:100'],
            'items.*.panel_a19'              => ['nullable', 'string', 'max:100'],
            'items.*.panel_a20'              => ['nullable', 'string', 'max:100'],
            'items.*.panel_milat_ru1213'     => ['nullable', 'string', 'max:100'],
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
