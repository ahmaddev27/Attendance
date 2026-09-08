<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A single unit of work. Phase 1 deliberately has no project_id/sprint_id
 * — those tables (and the columns that would reference them) are Phase 2;
 * see docs/v2/03-phase-1-plan.md's M6 section.
 */
class Task extends Model implements HasMedia
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, InteractsWithMedia, Searchable, SoftDeletes;

    protected $fillable = [
        'parent_task_id',
        'title',
        'description',
        'status_id',
        'priority_id',
        'created_by',
        'assigned_to',
        'estimated_hours',
        'actual_hours',
        'progress_percent',
        'start_date',
        'due_date',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress_percent' => 'integer',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
        ];
    }

    /**
     * Attachments live in a single 'attachments' collection with no
     * conversions for Phase 1 — thumbnails/previews are a later
     * refinement once there's a concrete UI need for them.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    /**
     * @return BelongsTo<TaskStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    /**
     * @return BelongsTo<TaskPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'priority_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * @return HasMany<TaskHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(TaskHistory::class);
    }

    /**
     * @return BelongsToMany<TaskTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TaskTag::class, 'task_tag_task');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'title' => (string) $this->title,
            'description' => (string) $this->description,
        ];
    }
}
