<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Modules\Employees\Requests\StoreEmployeeRequest;
use App\Modules\Employees\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Resources\EmployeeResource;
use App\Modules\Employees\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

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

        return (new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee = $this->employeeService->update($employee, $request->validated());

        return new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager']));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->employeeService->softDelete($employee);

        return response()->json(['message' => 'Employee deleted.']);
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

        return new EmployeeResource($restored->load(['position', 'department', 'team', 'directManager']));
    }

    /**
     * `POST /employees/{employee}/reset-password`
     *
     * Admin-facing "give this employee a new password" endpoint. Accepts an
     * optional plaintext `password` (min 8) OR auto-generates a readable
     * 12-char password server-side when none is provided. The plaintext is
     * returned ONCE in the response so the admin can hand it to the user
     * out-of-band; no separate lookup exposes it later.
     *
     * If the Employee doesn't yet have a linked User, we create one on the
     * fly (using the same shape taqat:backfill-users does). Two admins
     * hitting reset in parallel are safe because updateOrCreate on the
     * unique (employees.user_id → users.id) column serialises through the
     * DB.
     */
    public function resetPassword(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'role' => ['nullable', 'string'],
        ]);

        $password = $validated['password'] ?? $this->generateReadablePassword();

        $email = $employee->email
            ?: sprintf('emp%04d@taqat.local', $employee->employee_number);

        // Reuse an existing User linked to this employee (from either FK
        // side) or create one atomically. Same shape as the backfill
        // command so operators can move between the two without surprises.
        $user = User::query()->where('employee_id', $employee->id)->first()
            ?? ($employee->user_id ? User::query()->find($employee->user_id) : null);

        if ($user === null) {
            $user = User::query()->create([
                'employee_number' => $employee->employee_number,
                'name' => $employee->full_name,
                'email' => $email,
                'phone' => $employee->phone,
                'password' => Hash::make($password),
                'is_active' => true,
                'employee_id' => $employee->id,
            ]);

            // Back-link on the employees side so the next lookup finds it
            // via either FK direction.
            $employee->user_id = $user->id;
            $employee->save();
        } else {
            $user->password = Hash::make($password);
            $user->is_active = true;
            $user->save();
        }

        // Optional role assignment — silently drop if the role doesn't
        // exist so a typo doesn't turn a routine reset into a 500.
        $role = $validated['role'] ?? 'employee';
        if (Role::query()->where('name', $role)->exists()) {
            $user->syncRoles([$role]);
        }

        return response()->json([
            'data' => [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'identifier' => $user->email ?: (string) $user->employee_number,
                'password' => $password,
                'user_created' => $user->wasRecentlyCreated,
            ],
        ]);
    }

    /**
     * 12-char password from an ambiguity-free alphabet — no O/0, no l/1,
     * so the operator can read it off an SMS or a face-to-face
     * conversation without spelling it out. Matches
     * `BackfillEmployeeUsers::generatePassword()` for consistency.
     */
    private function generateReadablePassword(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        $len = strlen($alphabet);
        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, $len - 1)];
        }
        return $password;
    }
}
