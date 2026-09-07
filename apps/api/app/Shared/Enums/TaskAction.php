<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * The kind of change a single TaskHistory row records. TaskHistory is an
 * insert-only append log (see TaskHistory model) — every state-changing
 * action on a Task or its comments/attachments appends one row here
 * rather than mutating a previous one.
 *
 * `Updated` is a catch-all beyond the milestone spec's named list, for
 * meaningful field changes (due_date, progress_percent) that don't have a
 * dedicated action of their own — see TaskHistoryService for where it's
 * used and the M6 build report for the rationale.
 */
enum TaskAction: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';
    case StatusChanged = 'status_changed';
    case PriorityChanged = 'priority_changed';
    case Commented = 'commented';
    case AttachedFile = 'attached_file';
    case Completed = 'completed';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Updated = 'updated';
}
