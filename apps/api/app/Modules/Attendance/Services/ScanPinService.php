<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\Employee;
use App\Models\User;
use App\Modules\Attendance\Exceptions\ScanPinException;
use App\Modules\Attendance\Repositories\EmployeeScanPinRepository;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Sms\Services\SmsService;
use App\Shared\Enums\ScanPinSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Issues, rotates and verifies the 4-digit PIN that proves who is standing
 * at the public scan page. The PIN itself is only ever held in memory: it
 * is hashed before any write, sent by SMS, and returned to the caller of
 * reset() once.
 */
class ScanPinService
{
    private const REQUIRED_SETTING_KEY = 'attendance.scan_pin_required';

    private const SETTINGS_GROUP = 'attendance';

    private const MAX_FAILED_ATTEMPTS = 5;

    /**
     * PIN-only identity resolves by IP (we don't know the employee until
     * the lookup succeeds). Owner's call 2026-10-04: PINs are permanent
     * and must never feel "locked for the day" — the bucket is sized
     * well above normal typing noise at a busy shared kiosk, and the
     * lockout window below is now short (2 min, not 15) so even a
     * tripped bucket clears before anyone notices.
     *
     * Deliberately kept under the per-route HTTP throttles
     * (`throttle:30,1,scan-record` + `throttle:60,1,scan-status`) so the
     * service-level counter stays the first wall the admin sees — the
     * HTTP throttle has no Arabic message and would otherwise look like
     * a mystery outage to a non-technical operator.
     */
    private const MAX_IP_FAILED_ATTEMPTS = 25;

    /**
     * Owner's call 2026-10-04: cool-off trimmed from 15 min to 2 min so a
     * stray lockout never feels like "my PIN stopped working for the day".
     * Keeps a minimum back-off against pure brute-force bursts without
     * stranding a legitimate scanner behind a long wait.
     */
    private const LOCKOUT_SECONDS = 120;

    private const ISSUE_CHUNK_SIZE = 100;

    // Kiosk identity is PIN-only (see memory: project-pin-only-scan-decision),
    // so the SMS leads with the PIN and keeps the employee_number for the
    // employee's own records. %1$s is employee_number, %2$s is the PIN.
    private const PIN_SMS_TEMPLATE = "رمز الحضور الخاص بك في طاقات:\nرمز الحضور: %2\$s\nالرقم الوظيفي: %1\$s\nاستخدم رمز الحضور وحده عند مسح رمز QR، ولا تشاركه مع أحد.";

    public function __construct(
        private readonly EmployeeScanPinRepository $scanPins,
        private readonly ScanPinGenerator $generator,
        private readonly ScanPinHasher $hasher,
        private readonly SettingsService $settings,
        private readonly SmsService $sms,
    ) {}

    /**
     * Defaults to off so a deploy never locks out employees who have not
     * been issued a PIN yet.
     */
    public function isRequired(): bool
    {
        return $this->settings->get(self::REQUIRED_SETTING_KEY, null, '0') === '1';
    }

    public function setRequired(bool $required, bool $force, User $actor): void
    {
        if ($required && ! $force) {
            $this->assertEveryActiveEmployeeHasPin();
        }

        DB::transaction(function () use ($required, $force, $actor): void {
            $this->settings->set(self::REQUIRED_SETTING_KEY, $required ? '1' : '0', self::SETTINGS_GROUP);

            activity('attendance')
                ->causedBy($actor)
                ->withProperties(['required' => $required, 'forced' => $force])
                ->log('scan_pin_enforcement_changed');
        });
    }

    /**
     * @return array{required: bool, active_employees: int, with_pin: int, without_pin: int, without_pin_and_phone: int}
     */
    public function summary(): array
    {
        $coverage = $this->scanPins->activeEmployeeCoverage();

        return [
            'required' => $this->isRequired(),
            'active_employees' => $coverage['active'],
            'with_pin' => $coverage['with_pin'],
            'without_pin' => $coverage['active'] - $coverage['with_pin'],
            'without_pin_and_phone' => $coverage['without_pin_and_phone'],
        ];
    }

    /**
     * @return array{pin: string, sms_queued: bool}
     */
    public function reset(Employee $employee, User $actor): array
    {
        // Uniqueness check runs under the HMAC lookup column, excluding
        // this employee so a reset that happens to land on their current
        // PIN space does not false-conflict with themselves.
        $pin = $this->generator->generateUnique(
            fn (string $candidate): bool => $this->scanPins->isLookupHashTaken(
                $this->hasher->hash($candidate),
                $employee->id,
            ),
        );
        $pinHash = Hash::make($pin);
        $lookupHash = $this->hasher->hash($pin);

        DB::transaction(function () use ($employee, $actor, $pinHash, $lookupHash): void {
            $this->scanPins->upsertForEmployee($employee->id, $pinHash, $lookupHash, ScanPinSource::AdminReset, $actor->id);

            activity('attendance')
                ->causedBy($actor)
                ->performedOn($employee)
                ->log('scan_pin_reset');
        });

        // Earlier failed guesses say nothing about a freshly generated PIN,
        // and a reset is how a locked-out employee gets unblocked.
        RateLimiter::clear($this->attemptsKey($employee));

        return [
            'pin' => $pin,
            'sms_queued' => $this->hasPhone($employee) && $this->sendPinSms($employee, $pin),
        ];
    }

    /**
     * @return array{issued: int, sms_queued: int, without_phone: int}
     */
    public function issueMissing(User $actor): array
    {
        $totals = ['issued' => 0, 'sms_queued' => 0, 'without_phone' => 0];

        $this->scanPins->chunkActiveEmployeesWithoutPin(
            self::ISSUE_CHUNK_SIZE,
            function (Collection $employees) use ($actor, &$totals): void {
                foreach ($this->issueForChunk($employees, $actor) as [$employee, $pin]) {
                    $totals['issued']++;

                    if (! $this->hasPhone($employee)) {
                        $totals['without_phone']++;

                        continue;
                    }

                    if ($this->sendPinSms($employee, $pin)) {
                        $totals['sms_queued']++;
                    }
                }
            },
        );

        activity('attendance')
            ->causedBy($actor)
            ->withProperties($totals)
            ->log('scan_pin_bulk_issued');

        return $totals;
    }

    public function change(Employee $employee, string $currentPassword, string $pin, User $actor): void
    {
        if (! Hash::check($currentPassword, (string) $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'كلمة السر الحالية غير صحيحة.',
            ]);
        }

        if (! $this->generator->hasValidFormat($pin)) {
            throw ValidationException::withMessages([
                'pin' => 'رمز الحضور يجب أن يتكون من 4 أرقام.',
            ]);
        }

        if ($this->generator->isWeak($pin)) {
            throw ValidationException::withMessages([
                'pin' => 'رمز الحضور سهل التخمين. اختر أرقاماً غير متسلسلة أو مكررة.',
            ]);
        }

        $lookupHash = $this->hasher->hash($pin);

        if ($this->scanPins->isLookupHashTaken($lookupHash, $employee->id)) {
            // PIN-only identity cannot allow two employees to share a PIN,
            // even on self-service change — otherwise a kiosk scan under
            // that PIN would be ambiguous. Surface a tactical message so
            // the employee simply tries another PIN.
            throw ValidationException::withMessages([
                'pin' => 'هذا الرمز مُستخدم بالفعل. اختر رمزاً مختلفاً.',
            ]);
        }

        $pinHash = Hash::make($pin);

        DB::transaction(function () use ($employee, $actor, $pinHash, $lookupHash): void {
            $this->scanPins->upsertForEmployee($employee->id, $pinHash, $lookupHash, ScanPinSource::SelfService, $actor->id);

            activity('attendance')
                ->causedBy($actor)
                ->performedOn($employee)
                ->log('scan_pin_changed');
        });

        RateLimiter::clear($this->attemptsKey($employee));
    }

    /**
     * The lockout is checked before the PIN so a locked employee stays
     * locked even when the next guess happens to be right.
     *
     * @throws ScanPinException
     */
    public function verifyForScan(Employee $employee, ?string $pin): void
    {
        $attemptsKey = $this->attemptsKey($employee);

        if (RateLimiter::tooManyAttempts($attemptsKey, self::MAX_FAILED_ATTEMPTS)) {
            throw ScanPinException::lockedOut();
        }

        $scanPin = $this->scanPins->findForEmployee($employee->id);

        if ($scanPin === null) {
            throw ScanPinException::notIssued();
        }

        if ($pin === null || ! Hash::check($pin, $scanPin->pin_hash)) {
            RateLimiter::hit($attemptsKey, self::LOCKOUT_SECONDS);

            throw ScanPinException::invalidCredentials();
        }

        RateLimiter::clear($attemptsKey);
    }

    /**
     * PIN-only identity: given the typed PIN and the caller's IP, resolve
     * it to the unique employee that owns it (if any). HMAC(pin) → row is
     * the whole identity proof — the `pin_lookup_hash` UNIQUE index makes
     * two employees sharing a PIN impossible, and bcrypt still protects
     * `pin_hash` at rest against a DB dump.
     *
     * Rate limit is per-IP (10 failures / 15 min). We cannot key off an
     * employee here because we do not know who typed the PIN yet, and
     * keying off the typed PIN itself would let an attacker lock a known
     * PIN out of their employee. IP-level feels right: a shared kiosk
     * absorbs a few typos but shuts down a burst-guess attempt.
     *
     * @throws ScanPinException
     */
    public function resolveByPin(?string $pin, string $ipAddress): Employee
    {
        $ipKey = $this->ipAttemptsKey($ipAddress);

        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_IP_FAILED_ATTEMPTS)) {
            throw ScanPinException::lockedOut();
        }

        if ($pin === null || ! $this->generator->hasValidFormat($pin)) {
            RateLimiter::hit($ipKey, self::LOCKOUT_SECONDS);

            throw ScanPinException::invalidCredentials();
        }

        $lookupHash = $this->hasher->hash($pin);
        $scanPin = $this->scanPins->findByLookupHash($lookupHash);
        // Defense-in-depth: cross-check against the bcrypt column so a
        // misconfigured HMAC backfill (same lookup, wrong plaintext) still
        // fails identity. In practice an HMAC hit implies a bcrypt hit for
        // the correct PIN; this just makes that implicit assumption fail
        // loud rather than silent.
        $bcryptOk = $scanPin !== null && Hash::check($pin, $scanPin->pin_hash);

        // Legacy-row fallback: PINs issued before the HMAC lookup column
        // existed (prior to migration 2026_10_06_300001) have
        // `pin_lookup_hash = NULL`. Owner's 2026-10-04 note: "رمز الحضور
        // القديم لسا مش شغال". Rather than make the admin bulk-reissue
        // every PIN, bcrypt-match the typed PIN against the small set of
        // un-backfilled rows and, on a hit, backfill the lookup hash so
        // the next scan short-circuits through the fast index path.
        if (! $bcryptOk) {
            $scanPin = $this->scanPins->findByBcryptScan($pin);
            if ($scanPin !== null) {
                $this->scanPins->backfillLookupHash($scanPin, $lookupHash);
                $bcryptOk = true;
            }
        }

        if (! $bcryptOk) {
            RateLimiter::hit($ipKey, self::LOCKOUT_SECONDS);

            throw ScanPinException::invalidCredentials();
        }

        $employee = $scanPin->employee;

        if ($employee === null
            || $employee->status !== \App\Shared\Enums\EmployeeStatus::Active
            || ($employee->user !== null && ! $employee->user->is_active)
        ) {
            RateLimiter::hit($ipKey, self::LOCKOUT_SECONDS);

            throw ScanPinException::invalidCredentials();
        }

        RateLimiter::clear($ipKey);

        return $employee;
    }

    private function assertEveryActiveEmployeeHasPin(): void
    {
        $withoutPin = $this->scanPins->countActiveEmployeesWithoutPin();

        if ($withoutPin > 0) {
            throw ValidationException::withMessages([
                'required' => __('يوجد :count موظف نشط بدون رمز حضور. أرسل الرموز أولاً أو أكّد التفعيل.', ['count' => $withoutPin]),
            ]);
        }
    }

    /**
     * Hashing runs before the transaction opens so bcrypt's cost is never
     * paid while holding locks. Lookup hashes are checked for uniqueness
     * both against the DB AND within this chunk — otherwise two freshly
     * drawn PINs could happen to match before either is committed.
     *
     * @param  Collection<int, Employee>  $employees
     * @return list<array{0: Employee, 1: string}>
     */
    private function issueForChunk(Collection $employees, User $actor): array
    {
        $pins = [];
        $hashes = [];
        $lookupHashes = [];
        $takenInChunk = [];

        foreach ($employees as $employee) {
            $pin = $this->generator->generateUnique(
                function (string $candidate) use ($takenInChunk, $employee): bool {
                    $lookup = $this->hasher->hash($candidate);

                    return isset($takenInChunk[$lookup])
                        || $this->scanPins->isLookupHashTaken($lookup, $employee->id);
                },
            );

            $lookup = $this->hasher->hash($pin);
            $takenInChunk[$lookup] = true;

            $pins[$employee->id] = $pin;
            $hashes[$employee->id] = Hash::make($pin);
            $lookupHashes[$employee->id] = $lookup;
        }

        return DB::transaction(function () use ($employees, $pins, $hashes, $lookupHashes, $actor): array {
            $issued = [];

            foreach ($employees as $employee) {
                $created = $this->scanPins->createIfMissing(
                    $employee->id,
                    $hashes[$employee->id],
                    $lookupHashes[$employee->id],
                    ScanPinSource::BulkIssue,
                    $actor->id,
                );

                if ($created) {
                    $issued[] = [$employee, $pins[$employee->id]];
                }
            }

            return $issued;
        });
    }

    /**
     * An SMS outage must never undo a PIN that is already committed — the
     * admin can still read the PIN out or reset again.
     */
    private function sendPinSms(Employee $employee, string $pin): bool
    {
        try {
            $this->sms->send(
                to: (string) $employee->phone,
                body: sprintf(self::PIN_SMS_TEMPLATE, $employee->employee_number, $pin),
            );

            return true;
        } catch (Throwable $e) {
            Log::warning('[ScanPinService::sendPinSms] enqueue failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function hasPhone(Employee $employee): bool
    {
        return $employee->phone !== null && trim($employee->phone) !== '';
    }

    private function attemptsKey(Employee $employee): string
    {
        return 'scan-pin:'.$employee->id;
    }

    private function ipAttemptsKey(string $ipAddress): string
    {
        // Keep the string short enough to fit any cache driver's key limit
        // while still being a stable per-IP bucket. '0.0.0.0' is the
        // controller's fallback when the request has no detectable IP.
        return 'scan-pin:ip:'.$ipAddress;
    }
}
