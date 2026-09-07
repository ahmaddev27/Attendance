<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\AttendanceDevice;
use App\Modules\Attendance\Exceptions\InvalidQrTokenException;
use App\Modules\Attendance\Repositories\AttendanceDeviceRepository;

class QrTokenService
{
    public function __construct(
        private readonly AttendanceDeviceRepository $devices,
    ) {}

    /**
     * Resolve a scanned token to its active device.
     *
     * @throws InvalidQrTokenException when the token matches no active
     *                                 device, or the device's token has
     *                                 rotated past its validity window.
     */
    public function resolveDevice(string $token): AttendanceDevice
    {
        $device = $this->devices->findActiveByToken($token);

        if (! $device) {
            throw new InvalidQrTokenException('Invalid or unknown QR token.');
        }

        if ($device->isTokenExpired()) {
            throw new InvalidQrTokenException('This QR code has expired. Please rescan.');
        }

        return $device;
    }

    public function rotate(AttendanceDevice $device): AttendanceDevice
    {
        $device->rotateToken();

        return $device;
    }
}
