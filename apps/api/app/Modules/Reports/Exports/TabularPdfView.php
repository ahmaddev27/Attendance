<?php

declare(strict_types=1);

namespace App\Modules\Reports\Exports;

use Illuminate\Support\Collection;

/**
 * Typed payload for the row-per-record PDF reports, so the Blade templates
 * share one layout and a renamed field fails here rather than in a view.
 */
abstract class TabularPdfView
{
    /**
     * @param  list<string>  $headings
     * @param  Collection<int, list<int|float|string|null>>  $rows
     */
    public function __construct(
        public readonly array $headings,
        public readonly Collection $rows,
        public readonly string $generatedAt,
    ) {}

    abstract public function title(): string;
}
