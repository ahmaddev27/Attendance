<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskPriorityRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:50'],
            'code' => [
                'sometimes', 'string', 'max:20', 'alpha_dash',
                Rule::unique('task_priorities', 'code')->ignore($this->route('task_priority')),
            ],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
