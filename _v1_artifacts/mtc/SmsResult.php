<?php

namespace App\DataObjects;

class SmsResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $providerResponse = null,
        public readonly ?string $errorCode = null,
    ) {}

    public static function success(?string $response = null): self
    {
        return new self(true, $response);
    }

    public static function failure(string $errorCode, ?string $response = null): self
    {
        return new self(false, $response, $errorCode);
    }
}
