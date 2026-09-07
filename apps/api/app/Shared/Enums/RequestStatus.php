<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Lifecycle state of a generic workflow `Request` (M5).
 *
 * Draft -> Submitted -> Pending -> (Approved | Rejected | Returned), with
 * Cancelled reachable from Draft/Submitted/Pending/Returned (see
 * Request::canBeCancelled()). Completed is reserved for a future milestone
 * where an Approved request still has post-approval processing to finish;
 * today RequestService::submit()/ApprovalService::approve() move a
 * request straight to Approved once its workflow is exhausted (or has no
 * steps at all), the same way LeaveStatus::Approved is already a terminal
 * state for the Leaves module.
 *
 * A Returned request goes back to the employee for edits and is expected
 * to re-enter at Pending (current_step reset to the workflow's first step)
 * once resubmitted — see RequestService::resubmit().
 */
enum RequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
