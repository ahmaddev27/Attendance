<?php

declare(strict_types=1);

namespace App\Modules\Requests\Events;

use App\Models\Request as RequestModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a decision advances a request's current step, or brings
 * it to a final Approved state. No listeners exist yet in M5 — see
 * RequestSubmitted for the same notification-hook rationale.
 */
class RequestApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RequestModel $request,
    ) {}
}
