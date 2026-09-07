<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * A channel a Notification class can be delivered over. Matches the
 * `notification_preferences.channel` enum column exactly — see
 * 2026_09_11_100001_create_notification_preferences_table.php.
 */
enum NotificationChannel: string
{
    case Database = 'database';
    case Sms = 'sms';
    case Email = 'email';
    case Broadcast = 'broadcast';
}
