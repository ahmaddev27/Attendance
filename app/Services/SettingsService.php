<?php

namespace App\Services;

use App\Repositories\SettingRepository;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    public function __construct(private readonly SettingRepository $repo) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();
        if (! isset($settings[$key])) {
            return $default;
        }

        return $this->cast($settings[$key]['value'], $settings[$key]['type']);
    }

    public function set(string $key, mixed $value, string $type): void
    {
        $stored = match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        $this->repo->upsert($key, $stored, $type);
        Cache::forget(self::CACHE_KEY);
    }

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => $this->repo->all());
    }

    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) return null;

        return match ($type) {
            'boolean' => (bool) $value,
            'number' => is_numeric($value) ? $value + 0 : 0,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
