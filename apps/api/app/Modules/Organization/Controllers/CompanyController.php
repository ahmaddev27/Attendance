<?php

declare(strict_types=1);

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\Organization\Requests\StoreCompanyRequest;
use App\Modules\Organization\Requests\UpdateCompanyRequest;
use App\Modules\Organization\Requests\UploadCompanyLogoRequest;
use App\Modules\Organization\Resources\CompanyResource;
use App\Modules\Organization\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    public function __construct(
        private readonly CompanyService $companies,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search']);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return CompanyResource::collection($this->companies->paginate($filters, $perPage));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->companies->create($request->validated());

        return (new CompanyResource($company->loadCount('departments')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Company $company): CompanyResource
    {
        return new CompanyResource($company->loadCount('departments'));
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $company = $this->companies->update($company, $request->validated());

        return new CompanyResource($company->loadCount('departments'));
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->companies->delete($company);

        return response()->json(['message' => 'Company deleted.']);
    }

    public function uploadLogo(UploadCompanyLogoRequest $request, Company $company): CompanyResource
    {
        $company = $this->companies->uploadLogo($company, $request->file('logo'));

        return new CompanyResource($company->loadCount('departments'));
    }

    public function removeLogo(Company $company): CompanyResource
    {
        $company = $this->companies->removeLogo($company);

        return new CompanyResource($company->loadCount('departments'));
    }

    /**
     * `GET /companies/tree` — the full org tree in one payload.
     *
     * Shape per node:
     *   [{id, name, logo_url, departments: [{id, name, teams: [{id, name,
     *   employee_count}]}]}]
     *
     * The whole tree is eager-loaded with withCount so the response is a
     * fixed small number of queries (companies + departments + teams +
     * employees-count) regardless of org size — no N+1 per level.
     */
    public function tree(): JsonResponse
    {
        $companies = Company::query()
            ->with([
                'departments' => fn ($q) => $q->orderBy('name'),
                'departments.teams' => fn ($q) => $q->withCount('employees')->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        $data = $companies->map(fn (Company $company) => [
            'id' => $company->id,
            'name' => $company->name,
            'logo_url' => $company->logo_path
                ? Storage::disk('public')->url($company->logo_path)
                : null,
            'departments' => $company->departments->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'teams' => $department->teams->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'employee_count' => (int) ($team->employees_count ?? 0),
                ])->values(),
            ])->values(),
        ])->values();

        return response()->json(['data' => $data]);
    }
}
