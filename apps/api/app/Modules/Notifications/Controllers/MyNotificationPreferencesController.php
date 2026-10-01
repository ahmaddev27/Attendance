<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Notifications\Resources\NotificationPreferenceMatrixResource;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service preferences page. Both endpoints resolve the user from
 * the Sanctum session; there is no admin surface to edit another user's
 * matrix — a Phase 2 admin override would land as a separate controller.
 */
class MyNotificationPreferencesController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $preferences)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $stored = $this->preferences->allFor($user);

        return response()->json([
            'data' => (new NotificationPreferenceMatrixResource($stored))->toArray($request),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $this->preferences->setMany(
            $request->user(),
            $request->validated()['preferences'],
        );

        $stored = $this->preferences->allFor($request->user());

        return response()->json([
            'data' => (new NotificationPreferenceMatrixResource($stored))->toArray($request),
        ]);
    }
}
