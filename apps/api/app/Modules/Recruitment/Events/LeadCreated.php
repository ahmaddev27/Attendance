<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly ?User $actor = null,
    ) {}
}
