<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Turns a phone number the way people type it into the international digits
 * an SMS carrier dials. MTCSMS (Palestine) does not deliver to a local
 * 0599 123 456; it needs 970599123456.
 */
final class PhoneNumber
{
    /** Shorter than a landline without its trunk zero: not a phone. */
    private const MIN_TYPED_DIGITS = 7;

    /** E.164 caps a full international number at 15 digits. */
    private const MAX_DIGITS = 15;

    /**
     * A national number (no trunk zero) is at most 9 digits here; longer
     * input without a leading zero already starts with a country code.
     */
    private const MAX_NATIONAL_DIGITS = 9;

    /**
     * @param  string|null  $defaultCountryCode  used for numbers typed without one
     *                                           (a leading + is ignored); empty sends the digits as typed
     * @return string|null digits only, or null when the input is not a phone number
     */
    public static function toInternational(string $typed, ?string $defaultCountryCode): ?string
    {
        $typed = trim($typed);

        if ($typed === '' || preg_match('/[^\d\s()+\-.]/', $typed) === 1) {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', $typed);

        if (strlen($digits) < self::MIN_TYPED_DIGITS) {
            return null;
        }

        $international = self::withCountryCode($typed, $digits, (string) preg_replace('/\D+/', '', (string) $defaultCountryCode));

        return strlen($international) <= self::MAX_DIGITS ? $international : null;
    }

    private static function withCountryCode(string $typed, string $digits, string $countryCode): string
    {
        return match (true) {
            str_starts_with($typed, '+') => $digits,
            str_starts_with($digits, '00') => substr($digits, 2),
            $countryCode === '' => $digits,
            str_starts_with($digits, '0') => $countryCode.substr($digits, 1),
            strlen($digits) <= self::MAX_NATIONAL_DIGITS => $countryCode.$digits,
            default => $digits,
        };
    }
}
