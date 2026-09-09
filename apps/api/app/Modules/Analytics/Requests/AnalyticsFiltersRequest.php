<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Every analytics endpoint shares the same slice-and-dice knobs:
 *
 *   from  YYYY-MM-DD  (inclusive, defaults to today − 30d)
 *   to    YYYY-MM-DD  (inclusive, defaults to today)
 *   department_id  optional int   (narrow to one department)
 *   team_id        optional int   (narrow to one team)
 *   employee_id    optional int   (narrow to one employee)
 *
 * Presenting them as a single Request keeps validation and cache-key
 * derivation in one place — every service reads the normalised getters
 * below rather than re-parsing raw input.
 */
class AnalyticsFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            // Only enforce the order when `from` is actually present.
            // Laravel's `after_or_equal:from` reads the field literally
            // and 422s when the referenced field is empty — a user
            // picking only "إلى تاريخ" would break the whole page.
            'to' => array_filter([
                'nullable',
                'date_format:Y-m-d',
                $this->filled('from') ? 'after_or_equal:from' : null,
            ]),
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function from(): CarbonImmutable
    {
        $raw = $this->input('from');

        return $raw
            ? CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay()
            : CarbonImmutable::now()->subDays(30)->startOfDay();
    }

    public function to(): CarbonImmutable
    {
        $raw = $this->input('to');

        return $raw
            ? CarbonImmutable::createFromFormat('Y-m-d', $raw)->endOfDay()
            : CarbonImmutable::now()->endOfDay();
    }

    public function departmentId(): ?int
    {
        return $this->integerOrNull('department_id');
    }

    public function teamId(): ?int
    {
        return $this->integerOrNull('team_id');
    }

    public function employeeId(): ?int
    {
        return $this->integerOrNull('employee_id');
    }

    /**
     * Stable, filter-order-independent cache-key fragment. Every analytics
     * service composes its own `analytics:v1:{endpoint}:{this}` cache key so
     * two identical filter sets share one cached row across services.
     */
    public function cacheKey(): string
    {
        return sha1(json_encode([
            'from' => $this->from()->toDateString(),
            'to' => $this->to()->toDateString(),
            'department_id' => $this->departmentId(),
            'team_id' => $this->teamId(),
            'employee_id' => $this->employeeId(),
        ], JSON_THROW_ON_ERROR));
    }

    private function integerOrNull(string $key): ?int
    {
        $value = $this->input($key);

        return ($value === null || $value === '') ? null : (int) $value;
    }
}
