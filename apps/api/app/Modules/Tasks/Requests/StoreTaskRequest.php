<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parent_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],

            // Optional: TaskService::create() defaults to the first
            // status by sort_order when omitted.
            'status_id' => ['nullable', 'integer', 'exists:task_statuses,id'],
            'priority_id' => ['required', 'integer', 'exists:task_priorities,id'],

            // Optional: TaskService::create() defaults to the acting
            // user's own employee profile when omitted, for the common
            // case of an employee creating their own task.
            'created_by' => ['nullable', 'integer', 'exists:employees,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],

            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'actual_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'exists:task_tags,id'],
        ];
    }
}
