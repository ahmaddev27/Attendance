<?php

namespace App\DataObjects;

use App\Enums\FraudCheckStatus;

class FraudCheckResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly FraudCheckStatus $status,
    ) {}
}
