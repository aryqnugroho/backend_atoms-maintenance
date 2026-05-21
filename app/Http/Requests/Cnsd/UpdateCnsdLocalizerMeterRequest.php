<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCnsdLocalizerMeterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'merk'               => ['sometimes', 'nullable', 'string', 'max:60'],
            'type'               => ['sometimes', 'nullable', 'string', 'max:60'],
            'serial_number'      => ['sometimes', 'nullable', 'string', 'max:60'],
            'items'              => ['sometimes', 'array'],
            'items.*.id'         => ['required_with:items', 'integer'],
            'items.*.hasil_1'    => ['nullable', 'string', 'max:255'],
            'items.*.hasil_2'    => ['nullable', 'string', 'max:255'],
            'items.*.keterangan' => ['nullable', 'string'],
        ];
    }
}
