<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

class ScanPinGenerator
{
    public const LENGTH = 4;

    /**
     * Safety net on the uniqueness retry loop. With weak-pin filtering we
     * still have ~8k valid 4-digit PINs, so hitting this cap means the
     * organisation grew beyond what a 4-digit PIN space can safely cover
     * — surface that explicitly instead of returning a duplicate.
     */
    private const UNIQUE_MAX_ATTEMPTS = 60;

    /**
     * Patterns people pick first — keypad columns (2580/0852), repeated
     * pairs and year-like values — on top of the repeated and sequential
     * digits rejected below.
     */
    private const BLOCKLIST = ['1212', '2580', '0852', '1004', '2000', '1122'];

    public function generate(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), self::LENGTH, '0', STR_PAD_LEFT);
        } while ($this->isWeak($pin));

        return $pin;
    }

    /**
     * Draw a non-weak PIN that also satisfies the caller's uniqueness
     * predicate (typically "no other employee has this lookup hash yet").
     *
     * The predicate returns true when the PIN is already taken; we loop
     * until it returns false or we exhaust the attempt budget. The caller
     * is responsible for turning the raw PIN into whatever digest it
     * compares against — this generator only knows about digits.
     *
     * @param  callable(string): bool  $isTaken
     *
     * @throws \RuntimeException when the usable PIN space is exhausted
     */
    public function generateUnique(callable $isTaken): string
    {
        for ($attempt = 0; $attempt < self::UNIQUE_MAX_ATTEMPTS; $attempt++) {
            $pin = $this->generate();

            if (! $isTaken($pin)) {
                return $pin;
            }
        }

        throw new \RuntimeException(
            'Scan PIN space exhausted — too many active employees for 4-digit PINs. Reduce the active set or widen the PIN format.',
        );
    }

    public function hasValidFormat(string $pin): bool
    {
        return preg_match('/^\d{'.self::LENGTH.'}$/', $pin) === 1;
    }

    public function isWeak(string $pin): bool
    {
        return count(array_unique(str_split($pin))) === 1
            || $this->isRun($pin, 1)
            || $this->isRun($pin, -1)
            || in_array($pin, self::BLOCKLIST, true);
    }

    private function isRun(string $pin, int $step): bool
    {
        $digits = array_map('intval', str_split($pin));

        for ($i = 1, $count = count($digits); $i < $count; $i++) {
            if ($digits[$i] - $digits[$i - 1] !== $step) {
                return false;
            }
        }

        return true;
    }
}
