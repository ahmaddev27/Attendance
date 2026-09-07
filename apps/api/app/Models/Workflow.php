<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * Mirrors the `is_active` column's DB default so a freshly created,
     * in-memory model (before any explicit reload) already reflects it —
     * Eloquent does not otherwise know about column defaults it didn't set.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Always ordered by step_order — every consumer (firstStep(),
     * nextStepAfter(), the admin steps listing) relies on this rather than
     * re-sorting itself.
     *
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    /**
     * @return HasMany<RequestType, $this>
     */
    public function requestTypes(): HasMany
    {
        return $this->hasMany(RequestType::class);
    }

    public function firstStep(): ?WorkflowStep
    {
        return $this->steps()->first();
    }

    /**
     * The step immediately after the given one, or null once $step is the
     * last one — meaning the request is done routing and should complete.
     */
    public function nextStepAfter(WorkflowStep $step): ?WorkflowStep
    {
        return $this->steps()
            ->where('step_order', '>', $step->step_order)
            ->first();
    }
}
