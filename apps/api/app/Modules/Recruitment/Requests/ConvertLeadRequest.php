<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for POST /leads/{lead}/convert — bundles Client, Case, and
 * optionally an initial batch of Jobs into a single call so the whole
 * conversion happens in one transaction (LeadConversionService).
 *
 * `reuse_client_id` short-circuits Client creation: pick an existing
 * Client (usually surfaced by the FE after the dedup warning) rather
 * than inserting a new one that would collide on
 * UNIQUE (company_name, country).
 */
class ConvertLeadRequest extends FormRequest
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
            // Reuse an existing Client. When set, the client.* fields
            // below are ignored.
            'reuse_client_id' => ['nullable', 'integer', 'exists:clients,id'],

            // New Client payload — required only if reuse_client_id is
            // absent, enforced by required_without.
            'client' => ['sometimes', 'array'],
            'client.company_name' => ['required_without:reuse_client_id', 'string', 'max:200'],
            'client.country' => ['nullable', 'string', 'max:100'],
            'client.city' => ['nullable', 'string', 'max:100'],
            'client.industry' => ['nullable', 'string', 'max:100'],
            'client.company_size' => ['nullable', 'string', 'max:50'],
            'client.company_website' => ['nullable', 'string', 'max:255', 'url'],
            'client.address' => ['nullable', 'string', 'max:1000'],
            'client.tax_number' => ['nullable', 'string', 'max:50'],
            'client.payment_terms' => ['nullable', 'string', 'max:50'],
            'client.account_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'client.force' => ['sometimes', 'boolean'],

            // Case is always required — the whole point of the conversion
            // is to open at least one campaign.
            'case' => ['required', 'array'],
            'case.title' => ['required', 'string', 'max:200'],
            'case.description' => ['nullable', 'string', 'max:5000'],
            'case.owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'case.priority' => ['sometimes', 'string', 'in:low,normal,high,urgent'],
            'case.target_hires' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'case.started_at' => ['nullable', 'date'],
            'case.deadline' => ['nullable', 'date', 'after_or_equal:case.started_at'],

            // Jobs are optional — a Case can start empty and have jobs
            // added later. Each job must carry the shape jobs.store
            // enforces on its own, minus the case_id/pipeline_id which
            // the service fills in.
            'jobs' => ['sometimes', 'array'],
            'jobs.*.title' => ['required_with:jobs', 'string', 'max:200'],
            'jobs.*.department' => ['nullable', 'string', 'max:100'],
            'jobs.*.openings' => ['required_with:jobs', 'integer', 'min:1', 'max:65535'],
            'jobs.*.employment_type' => ['required_with:jobs', 'string', 'in:full_time,part_time,contract,intern,temporary'],
            'jobs.*.work_mode' => ['required_with:jobs', 'string', 'in:remote,onsite,hybrid'],
            'jobs.*.location' => ['nullable', 'string', 'max:200'],
            'jobs.*.salary_min' => ['nullable', 'numeric', 'min:0'],
            'jobs.*.salary_max' => ['nullable', 'numeric', 'min:0', 'gte:jobs.*.salary_min'],
            'jobs.*.salary_currency' => ['nullable', 'string', 'size:3'],
            'jobs.*.required_experience_years' => ['nullable', 'integer', 'min:0', 'max:99'],
            'jobs.*.education_level' => ['nullable', 'string', 'max:50'],
            'jobs.*.required_skills' => ['nullable', 'array'],
            'jobs.*.required_skills.*' => ['string', 'max:100'],
            'jobs.*.nice_to_have_skills' => ['nullable', 'array'],
            'jobs.*.nice_to_have_skills.*' => ['string', 'max:100'],
            'jobs.*.required_languages' => ['nullable', 'array'],
            'jobs.*.required_languages.*' => ['string', 'max:50'],
            'jobs.*.description' => ['nullable', 'string', 'max:10000'],
            'jobs.*.responsibilities' => ['nullable', 'string', 'max:10000'],
            'jobs.*.application_deadline' => ['nullable', 'date'],
            'jobs.*.target_start_date' => ['nullable', 'date'],
            'jobs.*.pipeline_id' => ['nullable', 'integer', 'exists:recruitment_pipelines,id'],
            'jobs.*.owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
