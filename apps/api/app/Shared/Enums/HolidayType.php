<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum HolidayType: string
{
    case Official = 'official';
    case Company = 'company';
    case Special = 'special';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'Official',
            self::Company => 'Company',
            self::Special => 'Special',
        };
    }
}
