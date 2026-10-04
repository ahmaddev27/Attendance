<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Avatar storage. Unlike the ID scan / contract (private disk, signed
 * downloads), avatars live on the PUBLIC disk because every admin list
 * renders them inline as a plain <img>; a signed URL per row would be
 * expired noise. The old file is removed only after the new path is
 * persisted, so a failed DB write never leaves the row pointing at nothing.
 */
class EmployeeAvatarService
{
    public const DISK = 'public';

    public function upload(Employee $employee, UploadedFile $file): Employee
    {
        $previous = $employee->avatar_path;
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $name = Str::uuid()->toString().'.'.$extension;

        $path = Storage::disk(self::DISK)->putFileAs("employee-avatars/{$employee->id}", $file, $name);

        $employee->forceFill(['avatar_path' => $path])->save();
        $this->deleteFile($previous);

        return $employee;
    }

    public function delete(Employee $employee): Employee
    {
        $previous = $employee->avatar_path;

        $employee->forceFill(['avatar_path' => null])->save();
        $this->deleteFile($previous);

        return $employee;
    }

    private function deleteFile(?string $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
