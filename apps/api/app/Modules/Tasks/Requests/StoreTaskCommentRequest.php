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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:task_comments,id'],

            // Resolved to user_ids by the frontend before submission —
            // see TaskCommentService's class doc.
            'mentions' => ['sometimes', 'nullable', 'array'],
            'mentions.*' => ['integer', 'exists:users,id'],
        ];
    }
}
