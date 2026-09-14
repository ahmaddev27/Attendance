<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use LogicException;

/**
 * A LogicException because reaching it is always a programming error: no
 * product flow edits or removes audit rows.
 */
final class ImmutableActivityLogException extends LogicException
{
    public static function updating(): self
    {
        return new self('Activity log entries are insert-only and cannot be updated.');
    }

    public static function deleting(): self
    {
        return new self('Activity log entries are insert-only and cannot be deleted; retention runs through activitylog:clean.');
    }
}
