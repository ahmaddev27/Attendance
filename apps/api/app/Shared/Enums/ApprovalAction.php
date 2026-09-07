<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * The decision recorded on a single RequestApproval row.
 *
 * Forwarded is distinct from the other three: it does not decide the
 * request one way or another, it only adds `forwarded_to_id` as an
 * additional, temporary approver for the *same* workflow step (see
 * ApprovalService::forward() and Request::scopePendingForApprover()).
 */
enum ApprovalAction: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Forwarded = 'forwarded';
}
