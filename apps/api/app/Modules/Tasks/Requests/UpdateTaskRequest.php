<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
            'parent_task_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status_id' => ['sometimes', 'integer', 'exists:task_statuses,id'],
            'priority_id' => ['sometimes', 'integer', 'exists:task_priorities,id'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'actual_hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'progress_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],

            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'exists:task_tags,id'],
        ];
    }

    /**
     * See StoreTaskRequest — the FE historically ships `tag_ids` on the
     * update dialog too.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('tags') && $this->has('tag_ids')) {
            $this->merge(['tags' => $this->input('tag_ids')]);
        }
    }
}
