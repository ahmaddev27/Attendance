<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Built-in lead sources — the defaults for OptionList::LeadSources.
 * The effective list is admin-editable from /settings, so validation
 * and pickers read OptionListService, never `LeadSource::cases()`.
 * A lead may carry a source that is no longer (or never was) a case.
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

    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'لينكدإن',
            self::Referral => 'إحالة',
            self::Website => 'الموقع',
            self::ExistingClient => 'عميل حالي',
            self::Partner => 'شريك',
            self::Email => 'بريد إلكتروني',
            self::DirectOutreach => 'تواصل مباشر',
            self::Event => 'فعالية',
            self::Other => 'أخرى',
        };
    }
}
