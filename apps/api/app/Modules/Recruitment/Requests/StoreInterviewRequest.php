<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\InterviewKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Schedule a new interview against the Application in the route. The
 * conditional "at least one of location/meeting_url" guard is applied
 * via withValidator so the error attaches to a specific field rather
 * than the root — matches the shape the admin UI expects.
 */
class StoreInterviewRequest extends FormRequest
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
            'kind' => ['nullable', Rule::in(array_map(fn (InterviewKind $k) => $k->value, InterviewKind::cases()))],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:500'],
            'meeting_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $kind = (string) ($this->input('kind') ?? InterviewKind::Internal->value);

            // Internal interviews need somewhere to meet — either a
            // physical room or a video link. Client interviews are
            // allowed to defer the venue (client picks the platform).
            if ($kind === InterviewKind::Internal->value) {
                $hasLocation = $this->filled('location') || $this->filled('meeting_url');
                if (! $hasLocation) {
                    $v->errors()->add('location', 'يجب تحديد موقع للمقابلة أو رابط الاجتماع.');
                }
            }
        });
    }
}
