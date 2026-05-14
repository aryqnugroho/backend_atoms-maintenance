<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(['mt', 'supervisor', 'technician'])],
            'signature' => [
                'required',
                'string',
                'starts_with:data:image/png;base64,',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Role penanda tangan harus diisi.',
            'role.in' => 'Role penanda tangan tidak valid.',
            'signature.required' => 'Signature harus diisi.',
            'signature.starts_with' => 'Signature harus berupa base64 PNG data URL.',
        ];
    }
}
