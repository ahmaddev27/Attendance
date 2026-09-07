<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaskTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskTag extends Model
{
    /** @use HasFactory<TaskTagFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
    ];

    /**
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_tag_task');
    }
}
