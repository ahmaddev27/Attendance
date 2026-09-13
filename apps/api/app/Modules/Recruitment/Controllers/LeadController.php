<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Modules\Recruitment\Requests\ConvertLeadRequest;
use App\Modules\Recruitment\Requests\StoreLeadRequest;
use App\Modules\Recruitment\Requests\UpdateLeadRequest;
use App\Modules\Recruitment\Resources\ClientResource;
use App\Modules\Recruitment\Resources\JobRequirementResource;
use App\Modules\Recruitment\Resources\LeadResource;
use App\Modules\Recruitment\Resources\LeadSummaryResource;
use App\Modules\Recruitment\Resources\RecruitmentCaseResource;
use App\Modules\Recruitment\Services\LeadConversionService;
use App\Modules\Recruitment\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HTTP entry point for Leads. Every write is delegated to LeadService or
 * LeadConversionService — this class only shapes the request/response
 * contracts (filters in, Resources out) so the service layer stays
 * reusable from console commands, tests, and future GraphQL surfaces.
 */
class LeadController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly LeadService $leads,
        private readonly LeadConversionService $conversion,
    ) {}

    public function index(HttpRequest $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'owner_id',
            'status',
            'source',
            'country',
            'industry',
            'search',
            'followup_from',
            'followup_to',
        ]);

        if ($request->boolean('active_only')) {
            $filters['active_only'] = true;
        }

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return LeadResource::collection($this->leads->paginate($filters, $perPage));
    }

    public function show(Lead $lead): LeadResource
    {
        return new LeadResource($this->leads->find($lead->id));
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = $this->leads->create($request->validated(), $this->actor($request));

        return (new LeadResource($lead))->response()->setStatusCode(201);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        return new LeadResource($this->leads->update($lead, $request->validated(), $this->actor($request)));
    }

    public function destroy(HttpRequest $request, Lead $lead): JsonResponse
    {
        $this->leads->delete($lead, $this->actor($request));

        return response()->json(null, 204);
    }

    /**
     * Kanban view — every non-terminal lead grouped by status, ready for
     * the FE's column layout. Filter passthrough (owner_id, source, ...)
     * lets a sales manager narrow to a single rep's board.
     */
    public function kanban(HttpRequest $request): JsonResponse
    {
        $filters = $request->only(['owner_id', 'source', 'country', 'industry', 'search']);

        $groups = $this->leads->kanban($filters);

        return response()->json([
            'data' => $groups->map(fn ($column) => LeadSummaryResource::collection($column)),
        ]);
    }

    public function convert(ConvertLeadRequest $request, Lead $lead): JsonResponse
    {
        $result = $this->conversion->convert($lead, $request->validated(), $this->actor($request));

        return response()->json([
            'data' => [
                'lead' => (new LeadResource($result['lead']))->toArray($request),
                'client' => (new ClientResource($result['client']))->toArray($request),
                'case' => (new RecruitmentCaseResource($result['case']))->toArray($request),
                'jobs' => JobRequirementResource::collection($result['jobs']),
            ],
        ], 201);
    }

    /**
     * CSV export of the filtered list. UTF-8 BOM prepended so Excel
     * picks up Arabic company names without a manual "import from text"
     * dance — mirrors AttendanceReportController::csv.
     */
    public function export(HttpRequest $request): StreamedResponse
    {
        $filters = $request->only([
            'owner_id',
            'status',
            'source',
            'country',
            'industry',
            'search',
            'followup_from',
            'followup_to',
        ]);

        if ($request->boolean('active_only')) {
            $filters['active_only'] = true;
        }

        $paginator = $this->leads->paginate($filters, 1000);

        $filename = 'leads-'.now()->format('Y-m-d-His').'.csv';

        return response()->stream(function () use ($paginator): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'lead_number',
                'company_name',
                'country',
                'city',
                'industry',
                'company_size',
                'source',
                'status',
                'owner_id',
                'contact_person',
                'contact_email',
                'contact_phone',
                'expected_hiring_volume',
                'last_contact_at',
                'next_followup_at',
                'created_at',
            ]);

            foreach ($paginator->items() as $lead) {
                /** @var Lead $lead */
                fputcsv($out, [
                    $lead->lead_number,
                    $lead->company_name,
                    $lead->country,
                    $lead->city,
                    $lead->industry,
                    $lead->company_size,
                    $lead->source,
                    $lead->status?->value,
                    $lead->owner_id,
                    $lead->contact_person,
                    $lead->contact_email,
                    $lead->contact_phone,
                    $lead->expected_hiring_volume,
                    $lead->last_contact_at?->toIso8601String(),
                    $lead->next_followup_at?->toIso8601String(),
                    $lead->created_at?->toIso8601String(),
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function actor(HttpRequest $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
