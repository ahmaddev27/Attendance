<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Materialises a row stream for the formats that cannot stream (XLSX, PDF,
 * JSON). CSV streams in constant memory; the others hold every row at once,
 * so an unbounded export would exhaust PHP's memory limit mid-request and
 * surface as an opaque 500 instead of an actionable message.
 */
final class BoundedRows
{
    public const MAX_ROWS = 5000;

    /**
     * @template TRow
     *
     * @param  iterable<int, TRow>  $rows
     * @return Collection<int, TRow>
     */
    public static function collect(iterable $rows): Collection
    {
        $collected = new Collection;

        foreach ($rows as $row) {
            if ($collected->count() >= self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'format' => sprintf('Too many rows for this format (max %d). Narrow the filters or use CSV.', self::MAX_ROWS),
                ]);
            }

            $collected->push($row);
        }

        return $collected;
    }
}
