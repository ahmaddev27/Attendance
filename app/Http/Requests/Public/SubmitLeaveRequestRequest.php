<?php

namespace App\Http\Requests\Public;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitLeaveRequestRequest extends FormRequest
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
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:1000',
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
