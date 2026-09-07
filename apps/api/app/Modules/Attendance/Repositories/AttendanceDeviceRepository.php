<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Models\AttendanceDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AttendanceDeviceRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return AttendanceDevice::query()->orderBy('name')->paginate($perPage);
    }

    public function find(int $id): ?AttendanceDevice
    {
        return AttendanceDevice::find($id);
    }

    public function findActiveByToken(string $token): ?AttendanceDevice
    {
        return AttendanceDevice::query()
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->first();
    }

    public function create(array $data): AttendanceDevice
    {
        return AttendanceDevice::create($data);
    }

    public function update(AttendanceDevice $device, array $data): AttendanceDevice
    {
        $device->update($data);

        return $device->refresh();
    }

    public function delete(AttendanceDevice $device): void
    {
        $device->delete();
    }
}
