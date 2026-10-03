<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * IPv4/IPv6 matcher for exact addresses and CIDR ranges.
 *
 * Shared between FraudGuardService (enforcement path on scan) and the
 * attendance resource layer (display-only "origin" badge). Keeping the
 * matcher as a stateless support class means the resource layer doesn't
 * need to carry a service dependency just to answer a yes/no question.
 */
final class IpMatcher
{
    /**
     * Returns true when the given IP matches any entry in the whitelist.
     * Entries may be exact IPs (v4 or v6) or CIDR ranges. An empty
     * whitelist always returns false — callers that want "no whitelist
     * means allow" must branch upstream (that is a policy decision,
     * not a matching one).
     *
     * @param  array<int, string>|null  $whitelist
     */
    public static function matchesAny(string $ip, ?array $whitelist): bool
    {
        if (empty($whitelist)) {
            return false;
        }

        foreach ($whitelist as $entry) {
            if (self::matches($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    public static function matches(string $ip, string $entry): bool
    {
        if (str_contains($entry, '/')) {
            return self::ipInCidrRange($ip, $entry);
        }

        return $ip === $entry;
    }

    public static function ipInCidrRange(string $ip, string $cidr): bool
    {
        // IPv6 addresses always carry a ':' (v4 never does), so route on
        // that instead of ip2long()'s v4-only quirks. A colon on either
        // side (ip OR subnet) means we're in v6 territory and must fall
        // through to the bytewise implementation — comparing a v4 to a v6
        // subnet (or vice versa) is a mismatch, not a match.
        if (str_contains($ip, ':') || str_contains($cidr, ':')) {
            return self::ipInCidrRangeV6($ip, $cidr);
        }

        [$subnet, $prefixLength] = explode('/', $cidr, 2);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $prefixLength = (int) $prefixLength;

        if ($ipLong === false || $subnetLong === false || $prefixLength < 0 || $prefixLength > 32) {
            return false;
        }

        $mask = $prefixLength === 0 ? 0 : (-1 << (32 - $prefixLength));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * IPv6 CIDR match. ip2long() only understands v4, so we normalise both
     * sides to their 16-byte packed form via inet_pton() and compare byte
     * by byte, applying the prefix as a bitmask on the boundary byte. A
     * mismatched address family (v4 ip vs v6 subnet) fails the pton parse
     * and returns false — same "not a match" outcome as any other invalid
     * input to the v4 branch.
     */
    public static function ipInCidrRangeV6(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $prefixLength] = explode('/', $cidr, 2);
        $prefixLength = (int) $prefixLength;

        if ($prefixLength < 0 || $prefixLength > 128) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        // Both must parse AND both must be v6 (16 bytes) — refusing a
        // v4/v6 cross-match keeps the branch honest.
        if ($ipBin === false || $subnetBin === false
            || strlen($ipBin) !== 16 || strlen($subnetBin) !== 16
        ) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainderBits = $prefixLength % 8;

        // Whole leading bytes must be identical.
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        // Then the partial byte on the boundary (if any) — mask off the
        // low bits and compare only the prefix's bit-width.
        if ($remainderBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainderBits)) & 0xFF);

        return (ord($ipBin[$fullBytes]) & ord($mask)) === (ord($subnetBin[$fullBytes]) & ord($mask));
    }
}
