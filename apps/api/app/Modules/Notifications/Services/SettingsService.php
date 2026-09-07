<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\Setting;
use App\Shared\Enums\SettingType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Thin read/write facade over the `settings` table (ported from the v1
 * pattern). A setting explicitly typed `encrypted` — every credential
 * this app stores there, e.g. `sms_password` — is transparently
 * encrypted on write and decrypted on read, so callers never handle the
 * ciphertext themselves.
 *
 * Two layers of caching:
 *  - a Laravel Cache entry (`settings.all`, 1 hour) so most requests
 *    never touch the `settings` table at all;
 *  - a request-local copy of that same array, so a single request that
 *    calls get() many times (e.g. MtcSmsGateway reading username,
 *    password, and sender) only decrypts each value once.
 * Both are invalidated together on every write.
 */
class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $requestCache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Persists $value under $key. Pass `encrypted: true` for anything
     * secret — the stored row is encrypted at rest and transparently
     * decrypted by every subsequent get().
     */
    public function set(string $key, mixed $value, bool $encrypted = false): void
    {
        $type = $encrypted ? SettingType::Encrypted : SettingType::String;
        $stored = $encrypted ? Crypt::encryptString((string) $value) : (string) $value;

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored, 'type' => $type]);

        $this->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->requestCache !== null) {
            return $this->requestCache;
        }

        return $this->requestCache = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => Setting::query()->get()->mapWithKeys(
                fn (Setting $setting) => [$setting->key => $this->decode($setting)]
            )->all(),
        );
    }

    /**
     * Drops both cache layers. Called automatically by set(); exposed
     * publicly so a settings-management UI can force a reload after a
     * bulk import without going through set() row by row.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->requestCache = null;
    }

    private function decode(Setting $setting): mixed
    {
        if ($setting->value === null) {
            return null;
        }

        return match ($setting->type) {
            SettingType::Encrypted => Crypt::decryptString($setting->value),
            SettingType::Boolean => (bool) $setting->value,
            SettingType::Integer => (int) $setting->value,
            SettingType::Json => json_decode($setting->value, true),
            SettingType::String => $setting->value,
        };
    }
}
