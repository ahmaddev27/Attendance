<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Exceptions\ImmutableActivityLogException;
use Spatie\Activitylog\Models\Activity;

/**
 * An audit row that can be edited or removed after the fact proves
 * nothing, so Eloquent writes to existing rows are refused outright.
 * Retention (`activitylog:clean`) still works because it issues a
 * builder-level DELETE, which never fires model events.
 */
class ActivityLog extends Activity
{
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ImmutableActivityLogException::updating();
        });

        static::deleting(function (): void {
            throw ImmutableActivityLogException::deleting();
        });
    }
}
