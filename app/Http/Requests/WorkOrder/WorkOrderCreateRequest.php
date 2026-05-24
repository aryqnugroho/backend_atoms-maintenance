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
        $isGm = $this->input('wo_type') === 'gm_directive';

        return [
            'wo_type' => ['required', 'string', Rule::in(WorkOrder::TYPES)],
            'division' => ['required', 'string', Rule::in(WorkOrder::DIVISIONS)],
            'shift_id' => ['nullable', 'integer'],
            'shift_type' => ['required', 'string', Rule::in(WorkOrder::SHIFT_TYPES)],
            'shift_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            // Manager/supervisor/technician IDs are rostering_user_ids.
            // The service maps them to local_users.id and creates rows lazily.
            // Hence no `exists:local_users,id` constraint here.
            // GM directive: manager_id is required (GM picks the MT); other
            // WO types may auto-resolve from rostering.
            'manager_id' => [$isGm ? 'required' : 'nullable', 'integer'],
            'supervisor_id' => ['nullable', 'integer'],
            'assigned_technician_id' => ['nullable', 'integer'],
            'has_supervisor' => ['sometimes', 'boolean'],

            // Personnel is optional. For wo_type='shift' the backend auto-fills
            // the technician list from the rostering shift+division when omitted.
            // For wo_type='personal' the WorkOrderService selects the assigned
            // technician via assigned_technician_id, so personnel can be empty.
            // For wo_type='gm_directive' there are no technicians.
            'personnel' => ['sometimes', 'array'],
            'personnel.*.user_id' => ['required_with:personnel', 'integer'],
            'personnel.*.role_label' => ['required_with:personnel', 'string', 'max:50'],

            // GM directive carries no output_types — it is a directive, not a
            // completed task. Other WO types still require at least one output.
            'output_types' => $isGm ? ['sometimes', 'array'] : ['required', 'array', 'min:1'],
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
            'wo_type.in' => 'Tipe work order harus shift, personal, atau gm_directive.',
            'division.required' => 'Divisi harus diisi.',
            'division.in' => 'Divisi harus CNSD atau TFP.',
            'shift_type.required' => 'Tipe shift harus diisi.',
            'shift_type.in' => 'Tipe shift harus pagi, siang, atau malam.',
            'shift_date.required' => 'Tanggal shift harus diisi.',
            'description.required' => 'Deskripsi harus diisi.',
            'manager_id.required' => 'Manager Teknik harus dipilih.',
            'personnel.required' => 'Minimal satu personel harus ditugaskan.',
            'personnel.min' => 'Minimal satu personel harus ditugaskan.',
            'output_types.required' => 'Minimal satu output harus dipilih.',
        ];
    }
}
