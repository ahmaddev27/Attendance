<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Models\Employee;
use App\Models\User;
use App\Modules\Employees\Repositories\EmployeeRepository;
use App\Modules\Sms\Services\SmsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly SmsService $sms,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->employees->paginate($filters, $perPage);
    }

    public function find(int $id): Employee
    {
        return $this->employees->findOrFail($id);
    }

    /**
     * Create an employee, atomically assign the next sequential
     * employee_number, provision a login user, and send the credentials
     * over SMS when a phone is on file.
     *
     * The max(employee_number) lookup is locked FOR UPDATE inside the
     * transaction so two concurrent create requests cannot both read the
     * same max and insert the same next number (the v1 race-condition
     * lesson this system was rebuilt to avoid).
     *
     * The welcome SMS is enqueued (SmsService::send) rather than sent
     * inline — the admin's create request must not block on the MTC
     * round-trip, and a carrier hiccup must not roll back the create.
     * Credentials are also returned in-memory on the model
     * (`generated_password`) so the calling controller can surface them
     * once to the admin as a fallback if the phone was empty.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Employee
    {
        // employee_number is always server-generated — never trust a
        // client-supplied value here, even if one slipped through.
        unset($data['employee_number']);

        $result = DB::transaction(function () use ($data) {
            $data['employee_number'] = $this->employees->maxEmployeeNumberForUpdate() + 1;

            $employee = $this->employees->create($data);

            // Provision the login user inside the same transaction so a
            // failure at either step rolls back both — never end up with
            // an employees row that can't sign in.
            $password = $this->generateReadablePassword();
            $this->provisionUser($employee, $password);

            return ['employee' => $employee, 'password' => $password];
        });

        // Outside the transaction: enqueue the welcome SMS (the SendSmsJob
        // is DB-transaction-aware via the queue driver, but keeping the
        // dispatch outside the closure makes the ordering obvious to
        // future readers).
        $this->sendWelcomeSms($result['employee'], $result['password']);

        // Expose the plaintext on the returned model so the controller can
        // fall back to showing it to the admin when SMS wasn't sent (no
        // phone, or SMS provider not configured). This is not persisted
        // and never leaves this in-memory instance.
        $result['employee']->generated_password = $result['password'];

        return $result['employee'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        unset($data['employee_number']);

        if (array_key_exists('direct_manager_id', $data) && $data['direct_manager_id'] !== null) {
            $this->assertManagerIsNotSelf($employee, (int) $data['direct_manager_id']);
        }

        return $this->employees->update($employee, $data);
    }

    public function assignManager(Employee $employee, ?int $managerId): Employee
    {
        if ($managerId !== null) {
            $this->assertManagerIsNotSelf($employee, $managerId);
        }

        return $this->employees->update($employee, ['direct_manager_id' => $managerId]);
    }

    /**
     * Off-boarding: soft-delete the employees row AND disable the linked
     * User (revoke every Sanctum token + flip is_active=false). Without
     * the User-side steps a terminated employee kept full API access
     * indefinitely — their bearer tokens never expired and their login
     * still worked because auth only checks users.is_active, not the
     * employee row's soft-delete state.
     */
    public function softDelete(Employee $employee): bool
    {
        return DB::transaction(function () use ($employee) {
            $user = $employee->user;
            if ($user !== null) {
                $user->tokens()->delete();
                $user->forceFill(['is_active' => false])->save();
            }

            return $this->employees->softDelete($employee);
        });
    }

    public function restore(int $id): Employee
    {
        $employee = $this->employees->findTrashedOrFail($id);

        return $this->employees->restore($employee);
    }

    /**
     * Reset the login password for an existing employee. Reuses the same
     * provisioning helper as create() so both flows share one code path
     * for user linking + role assignment.
     *
     * Returns an array so the controller can hand the plaintext to the
     * admin regardless of whether the welcome SMS attempt landed —
     * belt-and-braces for an ops task where losing the password would
     * lock the user out.
     *
     * @return array{employee: Employee, password: string, user_created: bool, identifier: string}
     */
    public function resetPassword(Employee $employee, ?string $password = null, ?string $role = null): array
    {
        $password ??= $this->generateReadablePassword();

        $user = $this->provisionUser($employee, $password, $role);

        $this->sendWelcomeSms($employee, $password);

        return [
            'employee' => $employee,
            'password' => $password,
            'user_created' => $user->wasRecentlyCreated,
            'identifier' => $user->email ?: (string) $user->employee_number,
        ];
    }

    private function assertManagerIsNotSelf(Employee $employee, int $managerId): void
    {
        if ($employee->exists && $managerId === $employee->id) {
            throw ValidationException::withMessages([
                'direct_manager_id' => 'An employee cannot be their own direct manager.',
            ]);
        }
    }

    /**
     * Create (or update the password on) the User row bound to this
     * employee, from either FK direction. Guaranteed to leave the two
     * models linked in both directions when it returns.
     */
    private function provisionUser(Employee $employee, string $plaintextPassword, ?string $role = null): User
    {
        $email = $employee->email
            ?: sprintf('emp%04d@taqat.local', $employee->employee_number);

        $user = User::query()->where('employee_id', $employee->id)->first()
            ?? ($employee->user_id ? User::query()->find($employee->user_id) : null);

        if ($user === null) {
            $user = User::query()->create([
                'employee_number' => $employee->employee_number,
                'name' => $employee->full_name,
                'email' => $email,
                'phone' => $employee->phone,
                'password' => Hash::make($plaintextPassword),
                'is_active' => true,
                'employee_id' => $employee->id,
            ]);

            $employee->user_id = $user->id;
            $employee->save();

            // New user — assign the default 'employee' role additively so
            // downstream policy checks work. syncRoles is intentionally NOT
            // used here (see below).
            if (Role::query()->where('name', 'employee')->exists()) {
                $user->assignRole('employee');
            }
        } else {
            $user->password = Hash::make($plaintextPassword);
            $user->is_active = true;
            $user->save();

            // Reset-password path: DO NOT touch roles unless the caller
            // explicitly requested a role change. The previous version
            // called syncRoles(['employee']) unconditionally, which stripped
            // super-admin (or any other role) off the target user — an
            // admin resetting the sole super-admin's password locked the
            // whole tenant out of admin functions. Now roles change only
            // when $role is explicitly non-null.
            if ($role !== null && Role::query()->where('name', $role)->exists()) {
                $user->syncRoles([$role]);
            }
        }

        // Revoke any Sanctum tokens minted before this password change so a
        // rotation actually rotates — otherwise a compromised token stays
        // valid forever after the password reset. Same call on new-user
        // provision is a no-op (no tokens yet) and cheap.
        $user->tokens()->delete();

        return $user;
    }

    /**
     * Enqueue the "your account is ready" SMS. No-ops silently when the
     * employee has no phone on file — the admin sees the plaintext
     * password in the create response and can hand it over out-of-band.
     *
     * Failures here are logged and swallowed so an SMS outage never
     * blocks the admin from finishing a create.
     */
    private function sendWelcomeSms(Employee $employee, string $plaintextPassword): void
    {
        if ($employee->phone === null || trim($employee->phone) === '') {
            return;
        }

        $identifier = $employee->email ?: (string) $employee->employee_number;
        $body = sprintf(
            "مرحباً %s،\nتم إنشاء حسابك على منصة TAQAT.\nاسم المستخدم: %s\nكلمة السر: %s\nيرجى تغييرها بعد أول تسجيل دخول.",
            $employee->full_name,
            $identifier,
            $plaintextPassword,
        );

        try {
            $this->sms->send(to: $employee->phone, body: $body);
        } catch (Throwable $e) {
            Log::warning('[EmployeeService::sendWelcomeSms] enqueue failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 12-char password from an ambiguity-free alphabet — no O/0, no l/1,
     * so the operator can read it off an SMS or a face-to-face
     * conversation without spelling it out. Matches
     * BackfillEmployeeUsers::generatePassword() for consistency.
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
