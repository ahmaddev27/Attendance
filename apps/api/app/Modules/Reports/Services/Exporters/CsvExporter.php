<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Exporters;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic CSV streaming: every report-specific exporter owns its own
 * column list (key + label) and pre-shapes its rows into plain
 * associative arrays, then hands both to stream() here so the actual
 * fputcsv/streamDownload plumbing exists in exactly one place.
 */
class CsvExporter
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, array{key: string, label: string}>  $columns
     */
    public function stream(Collection $rows, array $columns, string $filenamePrefix = 'report'): StreamedResponse
    {
        $filename = $filenamePrefix.'-'.date('YmdHis').'.csv';

        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel (the realistic consumer of these exports)
            // renders Arabic employee/department names correctly instead
            // of mojibake — plain UTF-8 without a BOM is routinely
            // misdetected as the system codepage by Excel on Windows.
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, array_column($columns, 'label'));

            foreach ($rows as $row) {
                fputcsv($out, array_map(
                    fn (array $column) => (string) (data_get($row, $column['key']) ?? ''),
                    $columns,
                ));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
