<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

class ScanPinGenerator
{
    public const LENGTH = 4;

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
