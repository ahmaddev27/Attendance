<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\AttendanceDevice;
use App\Modules\Attendance\Repositories\AttendanceDeviceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
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
        $data = $this->stripMissingColumns($data);

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
        return $this->repository->update($device, $this->stripMissingColumns($data));
    }

    /**
     * Drop any payload key whose column doesn't exist on the live table.
     * Defensive against the migration-drift we hit on prod: the code
     * calls out enforce_geo / enforce_ip but the DDL that adds them
     * (migrations 100002 and self-heal 100010) never actually applied
     * to the row, so INSERT/UPDATE crashes with SQLSTATE[42S22]. This
     * lets the save succeed for every column that IS there; the missing
     * ones are just silently ignored until an admin runs migrations
     * out-of-band.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripMissingColumns(array $data): array
    {
        static $existing = null;

        // Cached per request — Schema::getColumnListing hits the info
        // schema which we don't want to re-run on every save.
        if ($existing === null) {
            $existing = array_flip(Schema::getColumnListing('attendance_devices'));
        }

        return array_intersect_key($data, $existing);
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
