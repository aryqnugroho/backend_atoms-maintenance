<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Updating a CNSD Recorder Meter record only allows mutating item values
 * (hasil_server_a/b for section A, hasil for section B, keterangan).
 * Personnel, date, shift, signatures, form_number, merk/type/serial_number,
 * and form_code are never editable here. U/S items are silently ignored
 * by the service.
 */
class UpdateCnsdRecorderMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.id'                => ['required', 'integer'],
            'items.*.hasil_server_a'    => ['nullable', 'string', 'max:120'],
            'items.*.hasil_server_b'    => ['nullable', 'string', 'max:120'],
            'items.*.hasil'             => ['nullable', 'string', 'max:120'],
            'items.*.keterangan'        => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'      => 'Minimal satu item harus disertakan.',
            'items.array'         => 'Items harus berupa array.',
            'items.*.id.required' => 'Setiap item wajib menyertakan id.',
        ];
    }
}
