<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The FE mention picker returns Employee ids (from EmployeeSummary,
     * which is what the whole app uses for user pickers). We translate
     * to user_ids here before validation so downstream code can treat
     * `mentions` as user_ids uniformly. Employees with no linked User
     * are dropped silently — no login account → no one to notify.
     */
    protected function prepareForValidation(): void
    {
        $mentions = $this->input('mentions');
        if (! is_array($mentions) || $mentions === []) {
            return;
        }

        // If the array already contains user_ids (integers that resolve
        // to users), we leave them alone — supports both shapes for
        // forward-compat with a UI that later sends user_ids directly.
        // Employees have their own id space so a lookup on employee_id
        // returning nothing means the caller already sent user_ids.
        $translated = \App\Models\Employee::query()
            ->whereIn('id', $mentions)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        if ($translated !== []) {
            $this->merge(['mentions' => array_values(array_unique($translated))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:task_comments,id'],

            // Mentions arrive as employee_ids from the picker and are
            // translated to user_ids in prepareForValidation above.
            'mentions' => ['sometimes', 'nullable', 'array'],
            'mentions.*' => ['integer', 'exists:users,id'],
        ];
    }
}
