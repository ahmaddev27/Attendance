<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Employees\Requests\StoreEmployeeRequest;
use App\Modules\Employees\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Resources\EmployeeResource;
use App\Modules\Employees\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search', 'department_id', 'team_id', 'position_id',
            'status', 'employment_type', 'direct_manager_id',
        ]);

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return EmployeeResource::collection($this->employeeService->paginate($filters, $perPage));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeService->create($request->validated());

        return (new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager', 'workSchedule'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager', 'workSchedule']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee = $this->employeeService->update($employee, $request->validated());

        return new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager', 'workSchedule']));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->employeeService->softDelete($employee);

        return response()->json(['message' => 'Employee deleted.']);
    }

    /**
     * `GET /me/team` — teammates the current user is allowed to assign
     * tasks to. Public to any authenticated user; scoped to the caller's
     * own team_id (or their own row when they aren't on a team). Admins
     * with `manage-users` still get the full list via `/employees` — this
     * endpoint is the "who can I hand this task to" question a regular
     * employee needs answered, no PII beyond name+number.
     */
    public function myTeam(Request $request): AnonymousResourceCollection
    {
        $me = $request->user()?->employee;

        $query = Employee::query()
            ->where('status', \App\Shared\Enums\EmployeeStatus::Active)
            ->orderBy('first_name');

        if ($me?->team_id) {
            $query->where('team_id', $me->team_id);
        } elseif ($me) {
            // No team → the only teammate they can pick is themself.
            $query->where('id', $me->id);
        } else {
            // Admin without a linked employee record (bootstrap super-admin).
            // They already have manage-workflows so cross-team is allowed —
            // return the full active roster.
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        return EmployeeResource::collection($query->limit(50)->get());
    }

    /**
     * Restore a soft-deleted employee. Deliberately typed as `int` rather
     * than an `Employee` route binding — Laravel's default implicit model
     * binding excludes trashed models, which is exactly the record this
     * endpoint needs to find.
     */
    public function restore(int $employee): EmployeeResource
    {
        $restored = $this->employeeService->restore($employee);

        return new EmployeeResource($restored->load(['position', 'department', 'team', 'directManager', 'workSchedule']));
    }

    /**
     * `POST /employees/{employee}/reset-password`
     *
     * Admin-facing "give this employee a new password" endpoint. The new
     * password is ALWAYS server-generated (readable 12-char string) — the
     * client can no longer supply a `password` field, so a compromised
     * admin token can't install a known password silently. The generated
     * plaintext is returned ONCE in the response AND enqueued as a
     * welcome SMS to the employee's phone.
     */
    public function resetPassword(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string'],
        ]);

        $result = $this->employeeService->resetPassword(
            employee: $employee,
            password: null, // always server-generated — never accept client input
            role: $validated['role'] ?? null,
        );

        return response()->json([
            'data' => [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'identifier' => $result['identifier'],
                'password' => $result['password'],
                'user_created' => $result['user_created'],
            ],
        ]);
    }
}
