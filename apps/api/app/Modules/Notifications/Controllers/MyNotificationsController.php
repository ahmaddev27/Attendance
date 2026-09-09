<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Modules\Notifications\Resources\NotificationResource;

/**
 * The signed-in user's own inbox — bell dropdown + full list page.
 *
 * There is no admin view of every user's notifications; each user only
 * sees their own via $request->user()->notifications, which the Notifiable
 * trait wires up over the notifications table's morph.
 */
class MyNotificationsController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $onlyUnread = $request->boolean('unread');

        $query = $user->notifications();
        if ($onlyUnread) {
            $query->whereNull('read_at');
        }

        return NotificationResource::collection(
            $query->paginate((int) $request->integer('per_page', self::PER_PAGE))
        );
    }

    /**
     * Bell badge counter — cheap enough to hit on every page load. Kept
     * separate from index so the bell doesn't have to load a full page of
     * notifications when the user hasn't opened the dropdown yet.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['data' => new NotificationResource($notification->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        // Direct UPDATE on the relation query — the previous
        // `->unreadNotifications->markAsRead()` (property access) hydrated
        // every unread row into memory and looped one-by-one, which balloons
        // linearly with backlog. This is a single UPDATE ... WHERE read_at IS
        // NULL AND notifiable_* = ? with no hydration.
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['ok' => true]]);
    }
}
