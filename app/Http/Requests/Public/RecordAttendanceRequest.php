<?php

namespace App\Http\Requests\Public;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => [
                'required', 'integer',
                Rule::exists('employees', 'employee_number')->where('is_active', true),
            ],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_number.exists' => __('messages.scan_employee_not_found'),
        ];
    }

    public function employee(): Employee
    {
        return Employee::where('employee_number', $this->integer('employee_number'))->firstOrFail();
    }
}
