<?php

namespace App\Http\Requests\Grounding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Updating a Grounding Report record allows mutating item checklist values.
 * Personnel, date, shift, signatures, and report_number are never editable here.
 */
class UpdateGroundingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                => ['required', 'array', 'min:1'],
            'items.*.id'           => ['required', 'integer'],
            'items.*.availability' => ['sometimes', 'nullable', 'string', 'max:20'],
            'items.*.condition'    => ['sometimes', 'nullable', 'string', 'max:20'],
            'items.*.notes'        => ['sometimes', 'nullable', 'string', 'max:500'],
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
