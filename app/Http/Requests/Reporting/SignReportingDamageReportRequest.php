<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignReportingDamageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role'             => ['required', 'string', Rule::in(['manager', 'repairer'])],
            'signature'        => ['required', 'string', 'starts_with:data:image/png;base64,'],
            // For repairer role: which repairer row to sign. Optional — backend
            // resolves from authenticated user's local_users.id.
            'repairer_row_id'  => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required'         => 'Role tanda tangan harus diisi.',
            'role.in'               => 'Role harus salah satu dari: manager, repairer.',
            'signature.required'    => 'Signature harus diisi.',
            'signature.starts_with' => 'Signature harus berupa base64 PNG data URL.',
        ];
    }
}
