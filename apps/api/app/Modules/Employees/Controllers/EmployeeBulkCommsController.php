<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Requests\BulkEmailRequest;
use App\Modules\Employees\Requests\BulkSmsRequest;
use App\Modules\Employees\Services\EmployeeBulkCommsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin bulk-comms endpoints — one email or SMS fanned out to a selected
 * set of employees from the admin Employees page. Actual fan-out lives in
 * the service; this controller only validates, dispatches, and writes the
 * audit-log entry that records who sent to whom.
 */
class EmployeeBulkCommsController extends Controller
{
    public function __construct(
        private readonly EmployeeBulkCommsService $bulk,
    ) {}

    public function bulkEmail(BulkEmailRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->bulk->sendEmail(
            employeeIds: $data['employee_ids'],
            subject: (string) $data['subject'],
            body: (string) $data['body'],
        );

        $this->auditBulk($request, 'bulk_email_sent', [
            'target_count' => count($data['employee_ids']),
            'subject' => $data['subject'],
            'queued' => $result['queued'],
            'skipped_no_email' => $result['skipped_no_email'],
        ]);

        return response()->json(['data' => $result]);
    }

    public function bulkSms(BulkSmsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->bulk->sendSms(
            employeeIds: $data['employee_ids'],
            body: (string) $data['body'],
        );

        $this->auditBulk($request, 'bulk_sms_sent', [
            'target_count' => count($data['employee_ids']),
            'queued' => $result['queued'],
            'skipped_no_phone' => $result['skipped_no_phone'],
        ]);

        return response()->json(['data' => $result]);
    }

    /**
     * Trace the admin + the targeted employees so the audit-log viewer can
     * answer "who messaged X on day Y". We deliberately don't log the body
     * — it may contain personal reminders — but we do keep the subject on
     * the email path because audit entries without any context are useless
     * for actual investigations.
     *
     * @param  array<string, mixed>  $properties
     */
    private function auditBulk(Request $request, string $event, array $properties): void
    {
        activity('employees')
            ->causedBy($request->user())
            ->withProperties($properties)
            ->log($event);
    }
}
