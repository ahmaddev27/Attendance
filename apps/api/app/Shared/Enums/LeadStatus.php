<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Lifecycle state of a Lead in the sales pipeline.
 *
 *   New -> Contacted -> MeetingScheduled -> MeetingCompleted ->
 *   Qualified -> ProposalSent -> Negotiation -> Converted
 *
 * Lost and OnHold are reachable from any active state. Once Converted,
 * a Lead is terminal — LeadConversionService blocks re-conversion.
 * The set is kept intentionally close to industry-standard CRM stages
 * so a sales rep with prior HubSpot/Salesforce exposure needs no
 * training on what each state means.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case MeetingScheduled = 'meeting_scheduled';
    case MeetingCompleted = 'meeting_completed';
    case Qualified = 'qualified';
    case ProposalSent = 'proposal_sent';
    case Negotiation = 'negotiation';
    case Converted = 'converted';
    case Lost = 'lost';
    case OnHold = 'on_hold';
}
