<?php

namespace App\Http\Requests\Tfp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTfpTowerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'time_filled'      => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],

            'items'            => ['required', 'array', 'min:1'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.values'   => ['nullable', 'array'],
            'items.*.values.*' => ['nullable'],

            'facilities'              => ['sometimes', 'array'],
            'facilities.*.id'         => ['required_with:facilities', 'integer'],
            'facilities.*.kondisi'    => ['nullable', 'string', 'in:Baik,Rusak,Tidak Ada'],
            'facilities.*.keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'time_filled.regex'       => 'Waktu pengisian harus dalam format HH:MM.',
            'items.required'          => 'Minimal satu item harus disertakan.',
            'items.*.id.required'     => 'Setiap item wajib menyertakan id.',
            'facilities.*.kondisi.in' => 'Kondisi harus salah satu dari: Baik, Rusak, Tidak Ada.',
        ];
    }
}
