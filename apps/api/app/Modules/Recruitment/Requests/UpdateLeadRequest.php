<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\LeadSource;
use App\Shared\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH shape — every field is `sometimes` so partial updates work.
 * Rules that gate hard conversion (marking a lead Lost, transferring
 * ownership) live in LeadService rather than here; this class only
 * guards the shape of what's arriving.
 */
class UpdateLeadRequest extends FormRequest
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
            'company_name' => ['sometimes', 'string', 'max:200'],
            'company_website' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_size' => ['sometimes', 'nullable', 'string', 'max:50'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],

            'contact_person' => ['sometimes', 'nullable', 'string', 'max:150'],
            'contact_position' => ['sometimes', 'nullable', 'string', 'max:150'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'linkedin_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],

            'source' => ['sometimes', 'string', 'max:50', Rule::in(array_map(fn (LeadSource $c) => $c->value, LeadSource::cases()))],
            'status' => ['sometimes', 'string', Rule::in(array_map(fn (LeadStatus $c) => $c->value, LeadStatus::cases()))],

            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'expected_hiring_volume' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'last_contact_at' => ['sometimes', 'nullable', 'date'],
            'next_followup_at' => ['sometimes', 'nullable', 'date'],

            'lost_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
