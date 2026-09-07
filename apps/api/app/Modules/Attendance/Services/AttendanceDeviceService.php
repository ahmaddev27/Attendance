<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\AttendanceDevice;
use App\Modules\Attendance\Repositories\AttendanceDeviceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Not called out by name in the original module spec (which only listed
 * AttendanceService, WorkingHoursCalculator, FraudGuardService,
 * QrTokenService and WorkScheduleService), but added to keep device CRUD
 * out of the controller per the Controllers -> Services -> Repositories
 * layering used throughout the project.
 */
class AttendanceDeviceService
{
    private const DEFAULT_ROTATION_SECONDS = 300;

    public function __construct(
        private readonly AttendanceDeviceRepository $repository,
        private readonly QrTokenService $qrTokens,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AttendanceDevice
    {
        $data['qr_token'] = Str::random(64);
        $data['last_token_rotated_at'] = now();
        $data['qr_rotates_every_seconds'] ??= self::DEFAULT_ROTATION_SECONDS;

        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AttendanceDevice $device, array $data): AttendanceDevice
    {
        return $this->repository->update($device, $data);
    }

    public function delete(AttendanceDevice $device): void
    {
        $this->repository->delete($device);
    }

    public function rotateToken(AttendanceDevice $device): AttendanceDevice
    {
        return $this->qrTokens->rotate($device);
    }
}
