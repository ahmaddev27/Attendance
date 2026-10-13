<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeTasksReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the permission:view-reports route middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    public function year(): int
    {
        return $this->integer('year') ?: CarbonImmutable::now('Asia/Gaza')->year;
    }

    public function month(): ?int
    {
        return $this->filled('month') ? $this->integer('month') : null;
    }
}
