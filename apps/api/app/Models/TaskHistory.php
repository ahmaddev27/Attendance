<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\TaskAction;
use Database\Factories\TaskHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single insert-only entry in a task's audit trail. There is
 * deliberately no `update`/`delete` path on this model beyond what
 * Eloquent provides by default — TaskHistoryService only ever creates
 * rows here, never mutates or removes them.
 */
class TaskHistory extends Model
{
    /** @use HasFactory<TaskHistoryFactory> */
    use HasFactory;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    /**
     * Eloquent's default pluralization would guess `task_histories`; the
     * migration (and the M6 spec) names the table `task_history`.
     */
    protected $table = 'task_history';

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'action' => TaskAction::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
