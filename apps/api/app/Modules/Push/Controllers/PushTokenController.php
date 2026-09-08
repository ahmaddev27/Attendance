<?php

declare(strict_types=1);

namespace App\Modules\Push\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Modules\Push\Requests\RegisterPushTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for the mobile app to hand its Expo push token to the API.
 *
 *   POST   /me/push-tokens          — upsert on (user_id, device_id)
 *   DELETE /me/push-tokens          — revoke every token for this user
 *                                     (used on logout)
 *   DELETE /me/push-tokens/{device} — revoke a single device (logout on
 *                                      the current handset only, so a
 *                                      second logged-in device still
 *                                      receives push)
 *
 * All three require `auth:sanctum` — see routes/api.php.
 */
class PushTokenController extends Controller
{
    public function register(RegisterPushTokenRequest $request): JsonResponse
    {
        // Upsert on (user_id, device_id): a device that reopens the app
        // after Expo rotated its token still overwrites the previous
        // row in place instead of piling up duplicates.
        $token = PushToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'device_id' => $request->input('device_id'),
            ],
            [
                'token' => $request->input('token'),
                'platform' => $request->input('platform'),
                'device_name' => $request->input('device_name'),
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'device_id' => $token->device_id,
                'device_name' => $token->device_name,
                'created_at' => $token->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function revokeAll(Request $request): JsonResponse
    {
        $count = PushToken::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['data' => ['revoked' => (int) $count]]);
    }

    public function revokeDevice(Request $request, string $deviceId): JsonResponse
    {
        $count = PushToken::query()
            ->where('user_id', $request->user()->id)
            ->where('device_id', $deviceId)
            ->delete();

        return response()->json(['data' => ['revoked' => (int) $count]]);
    }
}
