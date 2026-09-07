<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only validates the request_type's own scalar fields plus the *presence*
 * of form_schema as an array — the schema's internal structure (field
 * types, required options for `select`, etc.) is a domain rule enforced by
 * RequestTypeService::validateSchema(), not plain HTTP input validation.
 */
class StoreRequestTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:request_types,code'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'workflow_id' => ['required', 'integer', 'exists:workflows,id'],
            'form_schema' => ['required', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
