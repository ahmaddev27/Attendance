<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Wraps the `notifications`/`unreadNotifications` relations Laravel's
 * Notifiable trait already puts on User, scoping every read/write to a
 * single user so a controller can never accidentally leak or mutate
 * someone else's notification center.
 */
class NotificationRepository
{
    public function paginateForUser(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return $user->notifications()->paginate($perPage);
    }

    public function unreadCountForUser(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Returns null if $notificationId does not exist or does not belong
     * to $user — the controller treats either case as a 404, never
     * revealing which one it was.
     */
    public function findForUser(User $user, string $notificationId): ?DatabaseNotification
    {
        return $user->notifications()->whereKey($notificationId)->first();
    }

    public function markAsRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllAsReadForUser(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
