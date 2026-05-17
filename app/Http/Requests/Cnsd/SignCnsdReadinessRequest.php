<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignCnsdReadinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role'           => ['required', 'string', Rule::in(['manager', 'supervisor', 'technician'])],
            'signature'      => ['required', 'string', 'starts_with:data:image/png;base64,'],
            // For technician role only: which row to sign. Optional — if omitted,
            // backend resolves it from the authenticated user's local_users.id.
            'technician_row_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required'        => 'Role tanda tangan harus diisi.',
            'role.in'              => 'Role harus salah satu dari: manager, supervisor, technician.',
            'signature.required'   => 'Signature harus diisi.',
            'signature.starts_with' => 'Signature harus berupa base64 PNG data URL.',
        ];
    }
}
