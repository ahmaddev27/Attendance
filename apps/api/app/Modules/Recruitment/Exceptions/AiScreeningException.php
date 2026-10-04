<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Exceptions;

use App\Modules\Attendance\Exceptions\AttendanceModuleException;
use Illuminate\Http\JsonResponse;

/**
 * Raised when the AI screening call cannot produce a usable result
 * (upstream failure, timeout, or a reply that is not the JSON we asked
 * for). Always 503: the recruiter's manual screening flow is unaffected,
 * so the UI shows a retry message instead of an error page.
 */
class AiScreeningException extends AttendanceModuleException
{
    public function statusCode(): int
    {
        return 503;
    }

    // Rendered here because the global handler has no mapping for the
    // shared module base type outside the attendance controller.
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'AI unavailable, try again later.'], $this->statusCode());
    }
}
