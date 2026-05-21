<?php

namespace App\Http\Requests\GroundCheck;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroundCheckLlzRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date'],
            'shift_type' => ['required', 'in:pagi,siang,malam'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'       => 'Tanggal wajib diisi.',
            'date.date'           => 'Format tanggal tidak valid.',
            'shift_type.required' => 'Shift wajib dipilih.',
            'shift_type.in'       => 'Shift harus salah satu dari: pagi, siang, malam.',
        ];
    }
}
