<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => [
                'sometimes', 'string', 'max:30', 'alpha_dash',
                Rule::unique('task_statuses', 'code')->ignore($this->route('task_status')),
            ],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer'],
            'is_done_state' => ['sometimes', 'boolean'],
            'is_cancelled_state' => ['sometimes', 'boolean'],
        ];
    }
}
