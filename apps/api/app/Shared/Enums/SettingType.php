<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * How a `settings.value` row should be cast on read/write — see
 * App\Modules\Notifications\Services\SettingsService.
 */
enum SettingType: string
{
    case String = 'string';
    case Encrypted = 'encrypted';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Json = 'json';
}
