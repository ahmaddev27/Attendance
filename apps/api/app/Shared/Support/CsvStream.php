<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvStream
{
    // Excel only detects UTF-8, and so renders Arabic, when the file opens with a BOM.
    private const UTF8_BOM = "\xEF\xBB\xBF";

    // Spreadsheet apps evaluate a cell starting with any of these as a
    // formula, which turns user-entered text into a CSV injection vector.
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    // A signed plain number such as an international phone number cannot
    // carry a formula, so it keeps its sign instead of gaining an apostrophe.
    private const SIGNED_PLAIN_NUMBER = '/^[+-]\d+(?:\.\d+)?$/';

    /**
     * Rows are pulled one at a time while the response is being sent, so a
     * lazy iterable keeps memory flat however many rows the export has.
     *
     * @param  list<string>  $header
     * @param  iterable<array<int, int|float|string|null>>  $rows
     */
    public function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($header, $rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, self::UTF8_BOM);

            $this->writeRow($out, $header);

            foreach ($rows as $row) {
                $this->writeRow($out, $row);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @param  resource  $out
     * @param  array<int, int|float|string|null>  $row
     */
    private function writeRow($out, array $row): void
    {
        // The escape character is spelled out because PHP 8.4 deprecates
        // relying on its default; "\\" keeps the historical output.
        fputcsv($out, array_map($this->neutraliseFormula(...), $row), ',', '"', '\\');
    }

    private function neutraliseFormula(int|float|string|null $value): int|float|string|null
    {
        if (! is_string($value) || $value === '' || preg_match(self::SIGNED_PLAIN_NUMBER, $value) === 1) {
            return $value;
        }

        return in_array($value[0], self::FORMULA_TRIGGERS, true) ? "'".$value : $value;
    }
}
