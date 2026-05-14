<?php

namespace App\Http\Requests\WorkOrder;

use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderOutput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wo_type' => ['required', 'string', Rule::in(WorkOrder::TYPES)],
            'division' => ['required', 'string', Rule::in(WorkOrder::DIVISIONS)],
            'shift_id' => ['nullable', 'integer'],
            'shift_type' => ['required', 'string', Rule::in(WorkOrder::SHIFT_TYPES)],
            'shift_date' => ['required', 'date'],
            'description' => ['required', 'string', 'min:10'],
            'manager_id' => ['nullable', 'integer', 'exists:local_users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:local_users,id'],
            'has_supervisor' => ['sometimes', 'boolean'],

            'personnel' => ['required', 'array', 'min:1'],
            'personnel.*.user_id' => ['required', 'integer', 'exists:local_users,id'],
            'personnel.*.role_label' => ['required', 'string', 'max:50'],

            'output_types' => ['required', 'array', 'min:1'],
            'output_types.*' => ['string', Rule::in(WorkOrderOutput::OUTPUT_TYPES)],
            'output_other' => ['nullable', 'string'],

            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'completion_status' => ['nullable', 'string', Rule::in(WorkOrder::COMPLETION_STATUSES)],
            'notes_kendala' => ['nullable', 'string'],
            'notes_usulan' => ['nullable', 'string'],
            'notes_pemberi_tugas' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'wo_type.required' => 'Tipe work order harus diisi.',
            'wo_type.in' => 'Tipe work order harus shift atau personal.',
            'division.required' => 'Divisi harus diisi.',
            'division.in' => 'Divisi harus CNSD atau TFP.',
            'shift_type.required' => 'Tipe shift harus diisi.',
            'shift_type.in' => 'Tipe shift harus pagi, siang, atau malam.',
            'shift_date.required' => 'Tanggal shift harus diisi.',
            'description.required' => 'Deskripsi harus diisi.',
            'description.min' => 'Deskripsi minimal 10 karakter.',
            'manager_id.required' => 'Manager Teknik harus dipilih.',
            'personnel.required' => 'Minimal satu personel harus ditugaskan.',
            'personnel.min' => 'Minimal satu personel harus ditugaskan.',
            'output_types.required' => 'Minimal satu output harus dipilih.',
        ];
    }
}
