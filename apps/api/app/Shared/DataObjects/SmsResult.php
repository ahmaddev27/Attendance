<?php

declare(strict_types=1);

namespace App\Shared\DataObjects;

/**
 * Outcome of a single SmsGatewayInterface::send() call. Ported unchanged
 * (aside from namespace) from v1 — see _v1_artifacts/mtc/SmsResult.php.
 */
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
