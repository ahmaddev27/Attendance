<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderStepsRequest extends FormRequest
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
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.id' => ['required', 'integer', 'distinct', 'exists:workflow_steps,id'],
            'steps.*.step_order' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
