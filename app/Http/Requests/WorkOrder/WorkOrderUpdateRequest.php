<?php

namespace App\Http\Requests\WorkOrder;

use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderOutput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'string', 'min:10'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'completion_status' => ['nullable', 'string', Rule::in(WorkOrder::COMPLETION_STATUSES)],
            'notes_kendala' => ['nullable', 'string'],
            'notes_usulan' => ['nullable', 'string'],
            'notes_pemberi_tugas' => ['nullable', 'string'],

            'output_types' => ['sometimes', 'array', 'min:1'],
            'output_types.*' => ['string', Rule::in(WorkOrderOutput::OUTPUT_TYPES)],
            'output_other' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.min' => 'Deskripsi minimal 10 karakter.',
            'output_types.min' => 'Minimal satu output harus dipilih.',
        ];
    }
}
