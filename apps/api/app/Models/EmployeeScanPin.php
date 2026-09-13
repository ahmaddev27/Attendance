<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\ScanPinSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hashed attendance PIN typed on the public scan page. Written only through
 * ScanPinService so every change is hashed, audited and rate-limit aware.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $pin_hash
 * @property ScanPinSource $set_via
 * @property ?int $set_by_user_id
 */
class EmployeeScanPin extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'pin_hash',
        'set_via',
        'set_by_user_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'pin_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'set_via' => ScanPinSource::class,
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }
}
