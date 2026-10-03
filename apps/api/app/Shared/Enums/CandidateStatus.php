<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Lifecycle marker on a Candidate row — ORTHOGONAL to any particular
 * application's status. "placed" means this person has been hired
 * somewhere by TAQAT (not necessarily on an application still open),
 * "blacklisted" removes them from new-application pickers, "inactive"
 * is a soft parking lot without the audit implication of
 * "blacklisted".
 */
enum CandidateStatus: string
{
    case Active = 'active';
    case Blacklisted = 'blacklisted';
    case Placed = 'placed';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Blacklisted => 'محظور',
            self::Placed => 'مُوظَّف',
            self::Inactive => 'غير نشط',
        };
    }
}
