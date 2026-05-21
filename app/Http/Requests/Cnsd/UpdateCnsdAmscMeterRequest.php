<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCnsdAmscMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merk'                 => ['sometimes', 'nullable', 'string', 'max:60'],
            'type'                 => ['sometimes', 'nullable', 'string', 'max:60'],
            'serial_number'        => ['sometimes', 'nullable', 'string', 'max:60'],
            'items'                => ['sometimes', 'array'],
            'items.*.id'           => ['required_with:items', 'integer'],
            'items.*.hasil_a'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.hasil_b'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.hasil'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.status_value' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.cct'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.keterangan'   => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
