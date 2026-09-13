<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Modules\Settings\Services\OptionListService;
use App\Shared\Enums\LeadStatus;
use App\Shared\Enums\OptionList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorisation happens at the route middleware layer via the
 * `manage-leads` permission; every request here just returns true and
 * lets the routes decide who can hit them.
 */
class StoreLeadRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:200'],
            'company_website' => ['nullable', 'string', 'max:255', 'url'],
            'industry' => ['nullable', 'string', 'max:100'],
            'company_size' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],

            'contact_person' => ['nullable', 'string', 'max:150'],
            'contact_position' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'linkedin_url' => ['nullable', 'string', 'max:255', 'url'],

            'source' => ['required', 'string', 'max:50', Rule::in(app(OptionListService::class)->values(OptionList::LeadSources))],
            'status' => ['sometimes', 'string', Rule::in(array_map(fn (LeadStatus $c) => $c->value, LeadStatus::cases()))],

            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'expected_hiring_volume' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'last_contact_at' => ['nullable', 'date'],
            'next_followup_at' => ['nullable', 'date'],

            // Force-create flag — set true after the API returned a
            // duplicate warning and the user opted to insert anyway.
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
