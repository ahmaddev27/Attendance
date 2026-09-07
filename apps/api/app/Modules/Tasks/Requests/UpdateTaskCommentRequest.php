<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskCommentRequest extends FormRequest
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
            'mentions' => ['sometimes', 'nullable', 'array'],
            'mentions.*' => ['integer', 'exists:users,id'],
        ];
    }
}
