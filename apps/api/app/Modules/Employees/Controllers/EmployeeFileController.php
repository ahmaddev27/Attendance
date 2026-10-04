<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Modules\Employees\Requests\UploadEmployeeAvatarRequest;
use App\Modules\Employees\Requests\UploadEmploymentContractRequest;
use App\Modules\Employees\Requests\UploadNationalIdImageRequest;
use App\Modules\Employees\Services\EmployeeAvatarService;
use App\Modules\Employees\Services\EmployeeFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin wrapper around EmployeeFileService for the two per-employee file
 * slots (national ID scan, employment contract). Each slot has three
 * verbs: upload, download (signed route), and delete. The upload/delete
 * endpoints are gated by `permission:manage-users` on the route layer;
 * the download endpoint additionally allows the employee themselves to
 * fetch their own files (self-service profile).
 */
class EmployeeFileController extends Controller
{
    public function __construct(
        private readonly EmployeeFileService $files,
        private readonly EmployeeAvatarService $avatars,
    ) {}

    public function uploadNationalId(UploadNationalIdImageRequest $request, Employee $employee): JsonResponse
    {
        $path = $this->files->replace(
            $employee,
            $request->file('file'),
            EmployeeFileService::KIND_NATIONAL_ID,
            'national_id_image_path',
        );

        return response()->json(['data' => ['path' => $path]], 201);
    }

    public function uploadContract(UploadEmploymentContractRequest $request, Employee $employee): JsonResponse
    {
        $path = $this->files->replace(
            $employee,
            $request->file('file'),
            EmployeeFileService::KIND_CONTRACT,
            'employment_contract_path',
        );

        return response()->json(['data' => ['path' => $path]], 201);
    }

    public function uploadAvatar(UploadEmployeeAvatarRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->avatars->upload($employee, $request->file('avatar'));

        return response()->json(['data' => ['avatar_url' => $employee->avatar_url]], 201);
    }

    public function deleteAvatar(Employee $employee): JsonResponse
    {
        $this->avatars->delete($employee);

        return response()->json(null, 204);
    }

    public function deleteNationalId(Employee $employee): JsonResponse
    {
        $this->files->remove($employee, 'national_id_image_path');

        return response()->json(null, 204);
    }

    public function deleteContract(Employee $employee): JsonResponse
    {
        $this->files->remove($employee, 'employment_contract_path');

        return response()->json(null, 204);
    }

    public function downloadNationalId(Request $request, Employee $employee): StreamedResponse
    {
        return $this->download($request, $employee, 'national_id_image_path');
    }

    public function downloadContract(Request $request, Employee $employee): StreamedResponse
    {
        return $this->download($request, $employee, 'employment_contract_path');
    }

    /**
     * Signed-URL download guard shared by both file kinds. On top of the
     * `signed` middleware (URL signature is valid and unexpired):
     *   - the caller must be authenticated;
     *   - the caller must EITHER carry `manage-users` (admin) OR be the
     *     employee whose file it is (self-service).
     * A leaked signed link is therefore still unusable without a session,
     * and even a logged-in peer cannot read another employee's documents.
     */
    private function download(Request $request, Employee $employee, string $column): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null, 401);

        $path = $employee->{$column};
        abort_unless(is_string($path) && $path !== '', 404);

        $isAdmin = false;
        try {
            $isAdmin = $user->hasPermissionTo('manage-users');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            // Missing permission definition == user does not have it.
        }
        $isSelf = $employee->user_id !== null && $employee->user_id === $user->id;

        abort_unless($isAdmin || $isSelf, 403, 'You do not have permission to download this file.');

        $disk = Storage::disk(EmployeeFileService::DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, basename($path));
    }
}
