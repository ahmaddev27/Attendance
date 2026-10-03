<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns the storage lifecycle for employee files handled OUTSIDE the main
 * resource create/update payload — specifically the national ID scan and
 * the signed employment contract.
 *
 * Why not Spatie MediaLibrary like TaskAttachment? Two reasons:
 *   1. One slot per kind — exactly one ID image, exactly one contract — so
 *      the extra collection table and per-slot queries buy nothing.
 *   2. The employee row sometimes exists before the file (admin edits an
 *      old record to add the ID scan) and sometimes the file is uploaded
 *      against an existing row. A plain Storage put against a stable path
 *      scheme fits both without needing a two-step media-pending flow.
 *
 * The chosen disk is `local` (private). A public URL would leak sensitive
 * PII (national ID numbers, salary clauses in the contract) to anyone who
 * guesses the UUID segment. Downloads go through signed routes that
 * additionally authorise the caller (admin or the employee themselves).
 */
class EmployeeFileService
{
    /**
     * Private disk — files are only reachable through signed download
     * routes, never via a public URL.
     */
    public const DISK = 'local';

    /** Top-level directory shared by every employee file kind. */
    public const DIRECTORY_PREFIX = 'employee-files';

    /** Sub-directory for national ID scans. */
    public const KIND_NATIONAL_ID = 'national-id';

    /** Sub-directory for employment contracts. */
    public const KIND_CONTRACT = 'contract';

    /**
     * Store an uploaded file for a given employee under
     *   employee-files/{employee_id}/{kind}/{uuid}.{ext}
     *
     * Namespacing by employee id makes orphan cleanup trivial (one directory
     * per employee) and keeps a stolen admin session from clobbering
     * unrelated employees' files by writing to a colliding path.
     */
    public function store(Employee $employee, UploadedFile $file, string $kind): string
    {
        $directory = $this->directoryFor($employee, $kind);
        $filename = $this->generateFilename($file);

        return $file->storeAs($directory, $filename, self::DISK);
    }

    /**
     * Replace the file at $column on the employee: writes the new upload,
     * updates the column in one transaction, and deletes the previous file
     * only AFTER the row commits — so a mid-write crash leaves the OLD file
     * still referenced instead of orphaning the row with a dead path.
     */
    public function replace(Employee $employee, UploadedFile $file, string $kind, string $column): string
    {
        $previous = $employee->{$column};
        $newPath = $this->store($employee, $file, $kind);

        DB::transaction(function () use ($employee, $column, $newPath): void {
            $employee->forceFill([$column => $newPath])->save();
        });

        if (is_string($previous) && $previous !== '' && $previous !== $newPath) {
            // Best-effort — a leftover file is far less harmful than a
            // dangling column pointing at nothing.
            Storage::disk(self::DISK)->delete($previous);
        }

        return $newPath;
    }

    /**
     * Clear the column and remove the file from disk. No-op when the
     * employee has no file in that slot.
     */
    public function remove(Employee $employee, string $column): void
    {
        $path = $employee->{$column};
        if (! is_string($path) || $path === '') {
            return;
        }

        DB::transaction(function () use ($employee, $column): void {
            $employee->forceFill([$column => null])->save();
        });

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Shared Closure used by StoreEmployeeRequest / UpdateEmployeeRequest to
     * authorise a client-supplied storage path. The path must live under
     * `employee-files/`, must not contain a traversal segment, and must
     * point at an existing file on the private disk.
     *
     * Kept here rather than inside the Request classes so the directory /
     * disk conventions live in exactly one place.
     */
    public static function pathValidationRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (str_contains($value, '..')) {
                $fail('غير مسموح — مسار الملف غير صالح.');

                return;
            }

            if (! str_starts_with($value, self::DIRECTORY_PREFIX.'/')) {
                $fail('غير مسموح — مسار الملف لا يخصّ الموظفين.');

                return;
            }

            if (! Storage::disk(self::DISK)->exists($value)) {
                $fail('الملف غير موجود.');
            }
        };
    }

    private function directoryFor(Employee $employee, string $kind): string
    {
        // Guard against a caller passing an unknown kind — the directory
        // scheme is a security surface and must stay closed to arbitrary
        // input even though the only callers are our own controllers.
        $kind = match ($kind) {
            self::KIND_NATIONAL_ID, self::KIND_CONTRACT => $kind,
            default => throw new \InvalidArgumentException("Unknown employee file kind: {$kind}"),
        };

        return self::DIRECTORY_PREFIX.'/'.$employee->id.'/'.$kind;
    }

    /**
     * A random slug + preserved extension keeps the file type clear for
     * downstream renderers while hiding the original (possibly PII-laden)
     * filename from logs and URLs.
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        return Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
    }
}
