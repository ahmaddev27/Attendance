<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Engagement state of a Client account.
 *
 *   Active     -> in current business with us
 *   OnHold     -> paused temporarily (finance issue, restructure)
 *   Inactive   -> no work in progress; kept for history / re-engagement
 *   Terminated -> relationship formally ended, no future work expected
 *
 * A Client is never physically deleted while historical Cases/Jobs
 * reference it — see the restrictOnDelete on recruitment_cases.client_id.
 */
enum ClientStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Inactive = 'inactive';
    case Terminated = 'terminated';
}
