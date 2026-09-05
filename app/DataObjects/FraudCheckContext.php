<?php

namespace App\DataObjects;

class FraudCheckContext
{
    public function __construct(
        public readonly ?string $ip,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
    ) {}
}
