<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecruitmentCaseRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', 'string', 'in:low,normal,high,urgent'],
            'status' => ['sometimes', 'string', Rule::in(array_map(fn (RecruitmentCaseStatus $c) => $c->value, RecruitmentCaseStatus::cases()))],
            'target_hires' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'deadline' => ['sometimes', 'nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
