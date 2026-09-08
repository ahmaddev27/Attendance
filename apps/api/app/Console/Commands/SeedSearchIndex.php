<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use Illuminate\Console\Command;

/**
 * One-shot reindexer for the four Scout-searchable models. Useful after
 * a fresh Meilisearch container comes up or when the searchable schema
 * has changed and every row needs to be re-pushed.
 *
 * Prefer this over `php artisan scout:import` per-model — it keeps every
 * indexed model in one place so ops runbooks stay short.
 */
class SeedSearchIndex extends Command
{
    /** @var string */
    protected $signature = 'search:seed';

    /** @var string */
    protected $description = 'Push every indexed model to the configured Scout engine.';

    /**
     * @var array<class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private const INDEXABLE_MODELS = [
        Employee::class,
        Task::class,
        RequestModel::class,
        LeaveRequest::class,
    ];

    public function handle(): int
    {
        foreach (self::INDEXABLE_MODELS as $model) {
            $this->info("Reindexing: {$model}");

            /** @var class-string<\Illuminate\Database\Eloquent\Model&\Laravel\Scout\Searchable> $model */
            $model::makeAllSearchable();
        }

        $this->info('Search index seeded.');

        return self::SUCCESS;
    }
}
