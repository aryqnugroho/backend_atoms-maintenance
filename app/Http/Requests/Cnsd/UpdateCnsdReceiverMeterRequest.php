<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCnsdReceiverMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merk'                => ['sometimes', 'nullable', 'string', 'max:60'],
            'type'                => ['sometimes', 'nullable', 'string', 'max:60'],
            'serial_number'       => ['sometimes', 'nullable', 'string', 'max:60'],
            'items'               => ['sometimes', 'array'],
            'items.*.id'          => ['required_with:items', 'integer'],
            'items.*.status_a'    => ['nullable', 'string', 'max:50'],
            'items.*.status_b'    => ['nullable', 'string', 'max:50'],
            'items.*.sequelsh_on' => ['nullable', 'string', 'max:255'],
            'items.*.keterangan'  => ['nullable', 'string'],
            'items.*.hasil'       => ['nullable', 'string', 'max:255'],
        ];
    }
}
