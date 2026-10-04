<?php

declare(strict_types=1);

use App\Models\LeaveRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

/**
 * Binary and streamed responses expose their body differently.
 */
function leavesExportBody($response): string
{
    $base = $response->baseResponse;

    if ($base instanceof BinaryFileResponse) {
        return (string) file_get_contents($base->getFile()->getPathname());
    }

    ob_start();
    $base instanceof StreamedResponse ? $base->sendContent() : print $base->getContent();

    return (string) ob_get_clean();
}

beforeEach(function (): void {
    $this->actingAsSuperAdmin();
    $year = now()->year;
    LeaveRequest::factory()->create(['start_date' => "{$year}-03-01", 'end_date' => "{$year}-03-02"]);
});

test('leaves csv stays the default and keeps its BOM and Arabic header', function () {
    $response = $this->get('/api/admin/reports/leaves/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
    // A streamed body can only be drained once.
    $body = $response->streamedContent();
    expect($body)->toStartWith("\xEF\xBB\xBF");
    expect($body)->toContain('اسم الموظف');
});

test('leaves xlsx returns a real workbook', function () {
    $response = $this->get('/api/admin/reports/leaves/export?format=xlsx');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('spreadsheetml');
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
    expect(substr(leavesExportBody($response), 0, 2))->toBe('PK');
});

test('leaves pdf returns a real PDF', function () {
    $response = $this->get('/api/admin/reports/leaves/export?format=pdf');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr(leavesExportBody($response), 0, 4))->toBe('%PDF');
});

test('leaves json returns the header and one row per leave', function () {
    $this->getJson('/api/admin/reports/leaves/export?format=json')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('leaves export rejects an unsupported format', function () {
    $this->getJson('/api/admin/reports/leaves/export?format=docx')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['format']);
});
