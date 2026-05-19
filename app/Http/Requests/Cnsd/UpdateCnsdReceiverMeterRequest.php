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
            'items'               => ['required', 'array'],
            'items.*.id'          => ['required', 'integer'],
            'items.*.status_a'    => ['nullable', 'string', 'max:50'],
            'items.*.status_b'    => ['nullable', 'string', 'max:50'],
            'items.*.sequelsh_on' => ['nullable', 'string', 'max:255'],
            'items.*.keterangan'  => ['nullable', 'string'],
            'items.*.hasil'       => ['nullable', 'string', 'max:255'],
        ];
    }
}
