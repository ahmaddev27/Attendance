<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Requests;

use App\Modules\Notifications\Services\NotificationPreferenceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk upsert for the signed-in user's notification preferences. The
 * controller is the only writer, so validating event_key / channel against
 * the service catalog here is both the integrity guard and the API's
 * contract — unknown keys are rejected rather than silently stored.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is behind auth:sanctum; every write targets $request->user()
        // so there is no cross-tenant surface.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var NotificationPreferenceService $service */
        $service = app(NotificationPreferenceService::class);
        $catalog = array_keys($service->knownEventKeys());

        return [
            // Hard cap on the payload size — the matrix only has
            // ~12 events × 6 channels, so anything above 200 is noise.
            'preferences' => ['required', 'array', 'min:1', 'max:200'],
            'preferences.*.event_key' => ['required', 'string', Rule::in($catalog)],
            'preferences.*.channel' => ['required', 'string', Rule::in(NotificationPreferenceService::CHANNELS)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
