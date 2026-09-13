<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A user-logged activity on a Lead. System-generated activities
 * (status_change, owner_change) bypass this class — LeadService writes
 * them straight through the repository.
 */
class StoreLeadActivityRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:call,meeting,email,note'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
        ];
    }
}
