<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value setting stored in the `settings` table. Reads/writes should
 * go through SettingsService — the model exists so Eloquent + activitylog
 * observers have something to hook into, not as the caller-facing API.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'encrypted', 'group'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
        ];
    }
}
