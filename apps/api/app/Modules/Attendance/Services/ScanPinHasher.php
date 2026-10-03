<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use RuntimeException;

/**
 * Keyed one-way hash used purely to find an employee by their 4-digit PIN
 * without an employee_number.
 *
 * The at-rest password hash on `pin_hash` is still bcrypt — that is what
 * resists DB dumps. This HMAC is a SECONDARY index whose only job is
 * `given PIN → one row` lookup, which bcrypt cannot do (its salt makes
 * every hash of the same PIN different). HMAC-SHA256 keyed by the Laravel
 * APP_KEY is deterministic (same PIN → same digest) AND server-only (an
 * attacker without APP_KEY cannot rainbow-table the 10k PIN space from a
 * leaked DB).
 *
 * Rotating APP_KEY invalidates every stored lookup — the admin bulk
 * reissue flow is the recovery path. That is the same policy Laravel's
 * encrypted casts already follow.
 */
class ScanPinHasher
{
    /**
     * Namespace tag mixed into the HMAC so this digest cannot collide
     * with any other HMAC we might derive from APP_KEY in the future.
     */
    private const DIGEST_CONTEXT = 'scan-pin:lookup:v1';

    public function hash(string $pin): string
    {
        $key = $this->key();

        return hash_hmac('sha256', self::DIGEST_CONTEXT.'|'.$pin, $key);
    }

    private function key(): string
    {
        $appKey = (string) config('app.key');

        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is not set — scan PIN lookup cannot be computed.');
        }

        // Laravel ships APP_KEY as "base64:..." — strip the prefix before
        // use so the derived HMAC does not depend on that envelope. If
        // APP_KEY ever stops starting with base64:, the else branch keeps
        // the hasher working against the raw string.
        return str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7), true) ?: $appKey
            : $appKey;
    }
}
