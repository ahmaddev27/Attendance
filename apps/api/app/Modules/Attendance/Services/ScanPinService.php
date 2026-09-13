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

    private const LOCKOUT_SECONDS = 900;

    private const ISSUE_CHUNK_SIZE = 100;

    private const PIN_SMS_TEMPLATE = 'رمز الحضور الخاص بك في طاقات هو: %s. استخدمه مع رقمك الوظيفي عند مسح رمز QR، ولا تشاركه مع أحد.';

    public function __construct(
        private readonly EmployeeScanPinRepository $scanPins,
        private readonly ScanPinGenerator $generator,
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
        $pin = $this->generator->generate();
        $pinHash = Hash::make($pin);

        DB::transaction(function () use ($employee, $actor, $pinHash): void {
            $this->scanPins->upsertForEmployee($employee->id, $pinHash, ScanPinSource::AdminReset, $actor->id);

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

        $pinHash = Hash::make($pin);

        DB::transaction(function () use ($employee, $actor, $pinHash): void {
            $this->scanPins->upsertForEmployee($employee->id, $pinHash, ScanPinSource::SelfService, $actor->id);

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
     * paid while holding locks.
     *
     * @param  Collection<int, Employee>  $employees
     * @return list<array{0: Employee, 1: string}>
     */
    private function issueForChunk(Collection $employees, User $actor): array
    {
        $pins = [];
        $hashes = [];

        foreach ($employees as $employee) {
            $pins[$employee->id] = $this->generator->generate();
            $hashes[$employee->id] = Hash::make($pins[$employee->id]);
        }

        return DB::transaction(function () use ($employees, $pins, $hashes, $actor): array {
            $issued = [];

            foreach ($employees as $employee) {
                if ($this->scanPins->createIfMissing($employee->id, $hashes[$employee->id], ScanPinSource::BulkIssue, $actor->id)) {
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
            $this->sms->send(to: (string) $employee->phone, body: sprintf(self::PIN_SMS_TEMPLATE, $pin));

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
}
