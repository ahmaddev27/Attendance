<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Partial update to a scheduled Interview. Status changes do NOT flow
 * through here — cancel/complete/reschedule each have their own
 * endpoint with their own guards.
 */
class UpdateInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedule-interviews') === true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'meeting_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
