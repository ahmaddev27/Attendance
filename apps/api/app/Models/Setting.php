<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\SettingType;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single generic key/value application setting (ported from the v1
 * pattern). The raw `value` column is always a string — encryption and
 * type-casting to the "real" PHP value are the responsibility of
 * App\Modules\Notifications\Services\SettingsService, not this model,
 * so a setting row read outside that service (e.g. Setting::all() in
 * a maintenance script) never accidentally leaks a decrypted secret.
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
        ];
    }
}
