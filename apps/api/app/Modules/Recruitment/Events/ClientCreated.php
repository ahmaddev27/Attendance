<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\Client;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Client $client) {}
}
