<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\JobRequirement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobRequirementSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly JobRequirement $job) {}
}
