<?php

declare(strict_types=1);

namespace App\Modules\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only validates the request's own scalar fields plus the *presence* of
 * form_data as an array — the field-by-field validation against the
 * chosen request type's dynamic form_schema is a domain rule enforced by
 * App\Modules\Requests\Services\FormSchemaValidator (invoked from
 * RequestService::submit()), not plain HTTP input validation, since it
 * depends on data (the request type's schema) this class would otherwise
 * have to duplicate the lookup for.
 */
class SubmitRequestRequest extends FormRequest
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
            'request_type_id' => ['required', 'integer', 'exists:request_types,id'],
            'form_data' => ['sometimes', 'array'],
        ];
    }
}
