<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * The two kinds of interview the plan recognises (Decision D4). One
 * table, polymorphic kind — the schema is identical, the only real
 * difference is who attends and whether the feedback is visible to
 * the client.
 */
enum InterviewKind: string
{
    case Internal = 'internal';
    case Client = 'client';
}
