<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\ClientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
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
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'string', 'max:50'],
            'payment_terms_notes' => ['nullable', 'string', 'max:2000'],

            'status' => ['sometimes', 'string', Rule::in(array_map(fn (ClientStatus $c) => $c->value, ClientStatus::cases()))],
            'account_manager_id' => ['nullable', 'integer', 'exists:users,id'],

            'notes' => ['nullable', 'string', 'max:5000'],

            // Force-create after a duplicate warning — same semantics as
            // StoreLeadRequest::force.
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
