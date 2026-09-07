<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RequestTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A submittable request type (e.g. "business mission", "advance",
 * "complaint"), pairing a Workflow (the approval chain) with a
 * `form_schema` describing the dynamic form the employee fills in.
 *
 * `form_schema` is a JSON array of field definitions, each shaped as:
 *
 * ```
 * [
 *   {
 *     "key": "reason",              // field name in form_data
 *     "label": "السبب",             // Arabic display label
 *     "type": "text|textarea|number|date|select|checkbox|file|employee",
 *     "required": true|false,
 *     "options": ["A", "B"],        // for type=select
 *     "min": 1, "max": 100,         // for type=number
 *     "placeholder": "..."
 *   }
 * ]
 * ```
 *
 * The schema's structural validity (allowed `type`, `options` present for
 * `select`, etc.) is enforced by
 * App\Modules\Workflow\Services\RequestTypeService::validateSchema()
 * whenever a request type is created/updated. A specific submission's
 * `form_data` is validated against this schema by
 * App\Modules\Requests\Services\FormSchemaValidator.
 */
class RequestType extends Model
{
    /** @use HasFactory<RequestTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The only field `type` values a form schema entry may declare. Single
     * source of truth shared by RequestTypeService (schema structure) and
     * FormSchemaValidator (submitted form_data).
     *
     * @var list<string>
     */
    public const FIELD_TYPES = [
        'text',
        'textarea',
        'number',
        'date',
        'select',
        'checkbox',
        'file',
        'employee',
    ];

    protected $fillable = [
        'name',
        'code',
        'description',
        'icon',
        'color',
        'workflow_id',
        'form_schema',
        'is_active',
        'sort_order',
    ];

    /**
     * Mirrors the `color`/`is_active`/`sort_order` columns' DB defaults so
     * a freshly created, in-memory model (before any explicit reload)
     * already reflects them — Eloquent does not otherwise know about
     * column defaults it didn't set.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => '#2678C4',
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'form_schema' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return HasMany<Request, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    /**
     * @param  Builder<RequestType>  $query
     * @return Builder<RequestType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
