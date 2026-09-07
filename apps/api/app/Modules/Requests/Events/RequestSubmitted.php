<?php

declare(strict_types=1);

namespace App\Modules\Requests\Events;

use App\Models\Request as RequestModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a request has been created and routed to its first workflow
 * step (or auto-approved, for a workflow with no steps). No listeners
 * exist yet in M5 — this is the hook the Notifications module wires an
 * "your request needs approval" listener onto in a later milestone.
 */
class RequestSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RequestModel $request,
    ) {}
}
