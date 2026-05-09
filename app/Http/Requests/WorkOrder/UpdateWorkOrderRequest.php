<?php

namespace App\Http\Requests\WorkOrder;

use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderOutput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'wo_type' => ['sometimes', 'string', Rule::in(WorkOrder::TYPES)],
            'division' => ['sometimes', 'string', Rule::in(WorkOrder::DIVISIONS)],
            'shift_type' => ['sometimes', 'string', Rule::in(WorkOrder::SHIFT_TYPES)],
            'shift_date' => ['sometimes', 'date'],
            'description' => ['sometimes', 'string', 'min:10'],
            'status' => ['sometimes', 'string', Rule::in(WorkOrder::STATUSES)],
            'manager_id' => ['sometimes', 'integer', 'exists:local_users,id'],
            'supervisor_id' => ['sometimes', 'integer', 'exists:local_users,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:local_users,id'],

            // Personnel array (optional on update)
            'personnel' => ['sometimes', 'array', 'min:1'],
            'personnel.*.user_id' => ['required_with:personnel', 'integer', 'exists:local_users,id'],
            'personnel.*.role_label' => ['required_with:personnel', 'string', 'max:50'],

            // Output types (optional on update)
            'output_types' => ['sometimes', 'array', 'min:1'],
            'output_types.*' => ['string', Rule::in(WorkOrderOutput::OUTPUT_TYPES)],
            'output_other' => ['nullable', 'string'],

            // Optional fields
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'completion_status' => ['nullable', 'string', Rule::in(WorkOrder::COMPLETION_STATUSES)],
            'notes_kendala' => ['nullable', 'string'],
            'notes_usulan' => ['nullable', 'string'],
            'notes_pemberi_tugas' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'wo_type.in' => 'Tipe work order harus shift atau personal.',
            'division.in' => 'Divisi harus CNSD atau TFP.',
            'shift_type.in' => 'Tipe shift harus pagi, siang, atau malam.',
            'description.min' => 'Deskripsi minimal 10 karakter.',
            'status.in' => 'Status harus completed, on_hold, atau ongoing.',
        ];
    }
}
