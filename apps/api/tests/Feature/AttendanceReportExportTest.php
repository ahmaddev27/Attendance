<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Employee;
use App\Shared\Enums\AttendanceStatus;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * maatwebsite/excel's `Excel::download()` returns a BinaryFileResponse and
 * DomPDF's `stream()` returns a StreamedResponse — the test client's
 * `streamedContent()` only handles the latter. This helper reads either
 * shape (plus the plain Response fallback) so the assertions below work
 * for every export format we ship without a per-test branch.
 */
function attendanceExportBody($response): string
{
    $base = $response->baseResponse ?? $response;

    if ($base instanceof BinaryFileResponse) {
        return (string) file_get_contents($base->getFile()->getPathname());
    }

    if ($base instanceof StreamedResponse) {
        // sendContent() writes to the output buffer; capture it so we
        // don't taint phpunit's own stdout.
        ob_start();
        $base->sendContent();
        return (string) ob_get_clean();
    }

    return (string) $response->getContent();
}

/**
 * Regression coverage for the XLSX + PDF admin monthly attendance
 * exports. The JSON and CSV formats are already exercised elsewhere;
 * these tests focus on the two file formats added in Phase 2.
 *
 * The service is called through the real HTTP layer so any middleware,
 * permission gate, or content-negotiation regression fails a test here
 * instead of in production.
 */

beforeEach(function (): void {
    actingAsAdmin();

    $employee = makeEmployeeWithSchedule();
    $today = now()->startOfMonth()->addDays(2)->toDateString();

    // A minimal attendance row so the report has content to serialize.
    Attendance::factory()->for($employee)->create([
        'date' => $today,
        'status' => AttendanceStatus::Present,
        'total_minutes' => 480,
        'overtime_minutes' => 30,
        'late_minutes' => 0,
    ]);
});

test('xlsx export returns a spreadsheetml attachment', function () {
    $year = (int) now()->format('Y');
    $month = (int) now()->format('n');

    $response = $this->get(
        "/api/admin/reports/attendance/monthly?year={$year}&month={$month}&format=xlsx"
    );

    $response->assertOk();

    // Spreadsheet MIME can be either the full OOXML type or a
    // vendor-suffixed variant depending on the response class — check
    // for the shared "spreadsheetml" fragment.
    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml');

    expect($response->headers->get('content-disposition'))
        ->toContain('.xlsx');

    // Not just headers — the body must contain the ZIP-based XLSX
    // signature so we know a real workbook streamed through.
    $body = attendanceExportBody($response);
    expect(strlen($body))->toBeGreaterThan(0);
    expect(substr($body, 0, 2))->toBe('PK');
});

test('pdf export returns a real PDF payload', function () {
    $year = (int) now()->format('Y');
    $month = (int) now()->format('n');

    $response = $this->get(
        "/api/admin/reports/attendance/monthly?year={$year}&month={$month}&format=pdf"
    );

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $body = attendanceExportBody($response);
    expect(substr($body, 0, 4))->toBe('%PDF');
});

test('unsupported format is rejected with validation error', function () {
    $year = (int) now()->format('Y');
    $month = (int) now()->format('n');

    $this->getJson(
        "/api/admin/reports/attendance/monthly?year={$year}&month={$month}&format=invalid"
    )->assertStatus(422)
        ->assertJsonValidationErrors(['format']);
});
