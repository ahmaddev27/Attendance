<?php

declare(strict_types=1);

namespace App\Modules\Reports\Requests;

use App\Shared\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportRequestReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_type_id' => ['nullable', 'integer', 'exists:request_types,id'],
            'status' => ['nullable', Rule::in(array_map(fn (RequestStatus $status) => $status->value, RequestStatus::cases()))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}
