<?php

declare(strict_types=1);

use App\Shared\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

function csvStreamBody(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

test('download streams a BOM, the header and every row as text/csv', function () {
    $response = (new CsvStream)->download('report.csv', ['رقم', 'الاسم'], (function () {
        yield [1, 'سارة'];
        yield [2, 'Omar Haddad'];
    })());

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename="report.csv"');
    expect(csvStreamBody($response))->toBe("\xEF\xBB\xBFرقم,الاسم\n1,سارة\n2,\"Omar Haddad\"\n");
});

test('cells that a spreadsheet would evaluate as a formula get a leading apostrophe', function () {
    $response = (new CsvStream)->download('report.csv', ['h'], [
        ['=1+1', '+1+cmd', '-2+3', '@SUM(A1)', "\tx", "\rx", 'safe', "it's", -5, 1.5, null, ''],
    ]);

    expect(csvStreamBody($response))->toBe(
        "\xEF\xBB\xBFh\n'=1+1,'+1+cmd,'-2+3,'@SUM(A1),\"'\tx\",\"'\rx\",safe,it's,-5,1.5,,\n"
    );
});

test('signed plain numbers such as international phone numbers are left untouched', function () {
    $response = (new CsvStream)->download('report.csv', ['h'], [
        ['+971501234567', '-5', '+2.5'],
    ]);

    expect(csvStreamBody($response))->toBe("\xEF\xBB\xBFh\n+971501234567,-5,+2.5\n");
});
