<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Updating a CNSD readiness record only allows mutating item values
 * (status_peralatan, kondisi_operasional_*, keterangan). Personnel,
 * date, shift, signatures, and form number are never editable here.
 */
class UpdateCnsdReadinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                                => ['required', 'array', 'min:1'],
            'items.*.id'                           => ['required', 'integer'],
            'items.*.status_peralatan'             => ['nullable', 'string', 'max:60'],
            'items.*.kondisi_operasional_1'        => ['nullable', 'string', 'max:80'],
            'items.*.kondisi_operasional_2'        => ['nullable', 'string', 'max:80'],
            'items.*.keterangan'                   => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item harus disertakan.',
            'items.array'    => 'Items harus berupa array.',
            'items.*.id.required' => 'Setiap item wajib menyertakan id.',
        ];
    }
}
