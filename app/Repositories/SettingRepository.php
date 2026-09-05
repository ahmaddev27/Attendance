<?php

namespace App\Repositories;

use App\Models\Setting;

class SettingRepository
{
    public function all(): array
    {
        return Setting::query()->get()->keyBy('key')->toArray();
    }

    public function upsert(string $key, ?string $value, string $type): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $type]);
    }
}
