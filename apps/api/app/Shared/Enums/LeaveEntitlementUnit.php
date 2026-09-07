<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * The unit a leave type's entitlement/balance figures are expressed in.
 *
 * Not yet wired to a database column — every M4 balance figure
 * (default_annual_entitlement, entitlement, used, pending,
 * carry_over_from_previous) is a whole/half-day count, matching
 * LeaveWorkingDaysCalculator's day-based output. This enum is defined now,
 * ahead of its column, as the seam a future milestone can use to let a
 * leave type opt into hourly accounting (e.g. a "short leave" type
 * measured in hours rather than days) without renaming anything already
 * shipped — the same forward-placeholder approach as
 * leave_requests.workflow_instance_id for M5.
 */
enum LeaveEntitlementUnit: string
{
    case Days = 'days';
    case Hours = 'hours';
}
