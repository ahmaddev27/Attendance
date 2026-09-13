<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecruitmentCaseRequest extends FormRequest
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
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'source_lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', 'string', 'in:low,normal,high,urgent'],
            'status' => ['sometimes', 'string', Rule::in(array_map(fn (RecruitmentCaseStatus $c) => $c->value, RecruitmentCaseStatus::cases()))],
            'target_hires' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'started_at' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
