<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Lifecycle state of a single LeaveRequest.
 *
 * Draft -> Pending -> Approved | Rejected, with Cancelled reachable from
 * Draft, Pending, or Approved (see LeaveRequest::canBeCancelled()). Once a
 * request is Approved, Rejected, or Cancelled it is considered decided and
 * cannot be approved/rejected again — LeaveRequestService enforces that
 * guard before every transition.
 */
enum LeaveStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
