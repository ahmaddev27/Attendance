<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Integrations\BrightGaza;

use RuntimeException;

final class BrightGazaUnavailableException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("BrightGaza job board is unavailable: {$reason}");
    }
}
