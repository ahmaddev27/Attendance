<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * The resolved state of a single employee/day attendance record.
 *
 * `Present`/`Late`/`EarlyLeave` are mutually exclusive outcomes computed by
 * WorkingHoursCalculator from a completed check-in/check-out pair. The
 * remaining cases describe days that never went through the scan flow at
 * all (holiday, weekend, leave, absence) or were recorded administratively
 * (remote, business mission).
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case EarlyLeave = 'early_leave';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case Weekend = 'weekend';
    case Remote = 'remote';
    case BusinessMission = 'business_mission';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::EarlyLeave => 'Early Leave',
            self::Absent => 'Absent',
            self::OnLeave => 'On Leave',
            self::Holiday => 'Holiday',
            self::Weekend => 'Weekend',
            self::Remote => 'Remote',
            self::BusinessMission => 'Business Mission',
        };
    }
}
