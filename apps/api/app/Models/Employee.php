<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\EmploymentType;
use App\Shared\Enums\Gender;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, Searchable, SoftDeletes;

    /**
     * `employee_number` is fillable here because it must be mass-assignable
     * for EmployeeRepository::create() to persist the value
     * EmployeeService::create() computes — client input never reaches this
     * far with it though: StoreEmployeeRequest/UpdateEmployeeRequest never
     * validate it, and EmployeeService::create()/update() explicitly strip
     * any client-supplied value before generating/persisting the real one.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_number',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position_id',
        'department_id',
        'team_id',
        'direct_manager_id',
        'employment_type',
        'joining_date',
        'birth_date',
        'gender',
        'avatar_path',
        'status',
        'notes',
        // Owned by the Attendance module (M3): `work_schedule_id` is added
        // to the `employees` table by its own migration
        // (2026_09_08_200005_add_work_schedule_to_employees.php). Listed
        // here so mass-assignment from that module's services works.
        'work_schedule_id',
    ];

    /**
     * Mirrors the `status` column's DB default so a freshly created,
     * in-memory model (before any explicit reload) already reflects it —
     * Eloquent does not otherwise know about column defaults it didn't set.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => EmployeeStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'birth_date' => 'date',
            'employment_type' => EmploymentType::class,
            'gender' => Gender::class,
            'status' => EmployeeStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function directManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'direct_manager_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'direct_manager_id');
    }

    /**
     * The department this employee manages, if any.
     *
     * @return HasOne<Department, $this>
     */
    public function managedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'manager_id');
    }

    /**
     * The team this employee leads, if any.
     *
     * @return HasOne<Team, $this>
     */
    public function ledTeam(): HasOne
    {
        return $this->hasOne(Team::class, 'leader_id');
    }

    /**
     * Owned by the Attendance module (M3).
     *
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /**
     * Owned by the Attendance module (M3).
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Flat payload pushed to Meilisearch. `id` is stringified because
     * Meilisearch treats the primary key as a string internally; keeping
     * `employee_number` numeric preserves range/sort semantics.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'employee_number' => (int) $this->employee_number,
            'full_name' => $this->full_name,
            'email' => (string) $this->email,
            'phone' => (string) $this->phone,
        ];
    }
}
