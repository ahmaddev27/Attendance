<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

/**
 * The status probe records nothing, so it needs no coordinates — but it
 * reveals today's attendance state, so it carries the same identity proof
 * as check-in/out.
 */
class ScanStatusRequest extends ScanIdentityRequest {}
