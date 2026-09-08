<?php

declare(strict_types=1);

namespace App\Modules\AI\Exceptions;

use RuntimeException;

/**
 * Thrown by ClaudeClient when the upstream call cannot produce a
 * usable message (non-2xx after retries, empty content, or a
 * network-level failure). MotivationService catches this and swaps in
 * a canned fallback so a bad API key never surfaces as a 500 on the
 * employee dashboard.
 */
class MotivationUnavailableException extends RuntimeException
{
}
