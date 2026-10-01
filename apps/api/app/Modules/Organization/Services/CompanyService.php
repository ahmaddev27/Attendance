<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Models\Company;
use App\Modules\Organization\Repositories\CompanyRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    /**
     * Directory under the `public` disk where company logos land. Kept
     * namespaced so a future "remove all logos" cleanup can target one
     * folder instead of walking the whole bucket.
     */
    private const LOGO_DIRECTORY = 'company-logos';

    public function __construct(
        private readonly CompanyRepository $companies,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Company>
     */
    public function list(array $filters): Collection
    {
        return $this->companies->list($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->companies->paginate($filters, $perPage);
    }

    public function find(int $id): Company
    {
        return $this->companies->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Company
    {
        return DB::transaction(fn () => $this->companies->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        return DB::transaction(fn () => $this->companies->update($company, $data));
    }

    /**
     * Refuse to delete a company that still holds departments — the same
     * guard pattern DepartmentService::delete uses for employees/teams.
     * Without this guard every department (and its employees + teams +
     * positions) would either cascade-delete or bubble a raw FK error.
     */
    public function delete(Company $company): bool
    {
        if ($company->departments()->count() > 0) {
            throw ValidationException::withMessages([
                'id' => 'لا يمكن حذف الشركة بينما تحتوي على أقسام. يرجى حذف أو نقل الأقسام أولاً.',
            ]);
        }

        return DB::transaction(function () use ($company) {
            // Clean the on-disk logo so a company tombstone doesn't leave
            // orphaned files behind. Safe to call when logo_path is null.
            $this->deleteLogoFile($company->logo_path);

            return $this->companies->delete($company);
        });
    }

    /**
     * Store a new logo for the company (replacing any previous file) and
     * persist the new path. Mirrors Employee avatar handling: validated
     * MIME + size caps happen in UploadCompanyLogoRequest, the service
     * only writes to disk and swaps the DB row.
     */
    public function uploadLogo(Company $company, UploadedFile $file): Company
    {
        return DB::transaction(function () use ($company, $file) {
            $previous = $company->logo_path;

            $path = $file->store(self::LOGO_DIRECTORY, 'public');

            $company = $this->companies->update($company, ['logo_path' => $path]);

            // Only after the DB commit is safe to swap in. Doing this
            // after the update means a failed save won't orphan the new
            // file either (transaction rollback keeps old row + we never
            // reached this line).
            $this->deleteLogoFile($previous);

            return $company;
        });
    }

    public function removeLogo(Company $company): Company
    {
        return DB::transaction(function () use ($company) {
            $this->deleteLogoFile($company->logo_path);

            return $this->companies->update($company, ['logo_path' => null]);
        });
    }

    private function deleteLogoFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
