<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * How the lead was acquired. Kept as an enum for validation defaults;
 * the effective list an Admin can pick from is stored in
 * `settings.recruitment.lead_sources` (JSON), so a new source added
 * from the admin UI shows up in the picker without a migration.
 * Never assume `LeadSource::cases()` is exhaustive at runtime — treat
 * this enum as the safe fallback.
 */
enum LeadSource: string
{
    case LinkedIn = 'linkedin';
    case Referral = 'referral';
    case Website = 'website';
    case ExistingClient = 'existing_client';
    case Partner = 'partner';
    case Email = 'email';
    case DirectOutreach = 'direct_outreach';
    case Event = 'event';
    case Other = 'other';
}
