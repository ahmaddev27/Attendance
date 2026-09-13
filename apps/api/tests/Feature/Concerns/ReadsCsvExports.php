<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use Illuminate\Testing\TestResponse;

trait ReadsCsvExports
{
    /**
     * Parsed rows of a streamed CSV download, header first, after asserting
     * the UTF-8 BOM Excel needs is in place.
     *
     * @return list<list<string|null>>
     */
    protected function csvRows(TestResponse $response): array
    {
        $body = $response->streamedContent();

        expect($body)->toStartWith("\xEF\xBB\xBF");

        $lines = array_filter(
            explode("\n", substr($body, strlen("\xEF\xBB\xBF"))),
            fn (string $line): bool => $line !== '',
        );

        return array_values(array_map(
            fn (string $line): array => str_getcsv($line, ',', '"', '\\'),
            $lines,
        ));
    }

    /**
     * One column of the data rows, header excluded.
     *
     * @return list<string|null>
     */
    protected function csvColumn(TestResponse $response, int $column): array
    {
        return array_column(array_slice($this->csvRows($response), 1), $column);
    }
}
