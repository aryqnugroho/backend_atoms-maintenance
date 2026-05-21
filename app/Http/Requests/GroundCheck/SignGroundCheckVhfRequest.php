<?php

namespace App\Http\Requests\GroundCheck;

use Illuminate\Foundation\Http\FormRequest;

class SignGroundCheckVhfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role'              => ['required', 'in:manager,supervisor,technician'],
            'signature'         => ['required', 'string'],
            'technician_row_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required'      => 'Role wajib diisi.',
            'role.in'            => 'Role harus salah satu dari: manager, supervisor, technician.',
            'signature.required' => 'Signature wajib diisi.',
        ];
    }
}
