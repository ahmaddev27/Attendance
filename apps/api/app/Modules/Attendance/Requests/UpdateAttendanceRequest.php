<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use App\Shared\Enums\AttendanceStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin manual correction of an attendance row. Permissions are enforced
 * on the route (`permission:view-all-attendance` plus a stricter inner
 * check inside the service). Every field is optional — the service only
 * touches the ones that arrive and re-runs the hours engine afterwards.
 */
class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-all-attendance') === true;
    }

    /**
     * A timestamp without zone info is what the admin typed on the wall
     * clock; owner's standing rule anchors it to Asia/Gaza, never the
     * server or browser zone. Only keys actually sent are touched so the
     * `sometimes` rules keep working.
     */
    protected function prepareForValidation(): void
    {
        $normalised = [];
        foreach (['check_in_at', 'check_out_at'] as $key) {
            if ($this->has($key)) {
                $normalised[$key] = $this->normaliseGaza($this->input($key));
            }
        }
        $this->merge($normalised);
    }

    /**
     * Returns the instant as a wall-clock string in the app timezone —
     * Eloquent's datetime cast stores an offset-bearing string's wall
     * clock verbatim (no conversion), so an unconverted "+03:00" value
     * would land shifted whenever APP_TIMEZONE differs from the offset.
     */
    private function normaliseGaza(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            return $value;
        }
        try {
            // Explicit Z / +HH:MM wins; a naive string is Gaza wall-clock.
            return Carbon::parse($value, 'Asia/Gaza')
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $value; // let the `date` rule reject it
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // `sometimes|nullable` lets an admin clear check_out_at (reopen
            // the day) while still rejecting an unknown field name.
            'check_in_at' => ['sometimes', 'nullable', 'date'],
            'check_out_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:check_in_at'],
            'status' => ['sometimes', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_out_at.after_or_equal' => 'لا يمكن أن يسبق وقت الانصراف وقت الحضور.',
        ];
    }
}
