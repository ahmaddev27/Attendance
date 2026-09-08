<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * DB-backed key/value settings with a Redis cache in front.
 *
 * Every caller — mail transport, MTC SMS gateway, feature-flags — reads
 * through this service so:
 *   1. Sensitive values (Resend key, MTC password) can be encrypted at rest
 *      via Crypt::encryptString() without callers touching the crypto.
 *   2. Cache invalidation is a single call away — bulk save() flushes once
 *      instead of per-key.
 *   3. Falls back to config() (which reads env()) when a key isn't in the
 *      database — so a fresh install without DB rows still works from .env.
 *
 * Keys are namespaced like `mail.resend_key`, `sms.mtc_username`. The
 * `group` column mirrors the prefix so the admin UI can render one section
 * per group.
 */
class SettingsService
{
    /** Cache TTL (seconds) for a single get() lookup. */
    private const CACHE_TTL = 3600;

    /** Cache key prefix for individual settings. */
    private const CACHE_PREFIX = 'settings:v1:';

    /**
     * Return the raw value for a key. Decrypts if the row is marked encrypted.
     * Falls back to config($fallbackConfigKey) when the DB row is missing,
     * which lets .env keep working during a fresh install.
     */
    public function get(string $key, ?string $fallbackConfigKey = null, ?string $default = null): ?string
    {
        $cached = Cache::get(self::CACHE_PREFIX.$key);
        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        $row = Setting::query()->where('key', $key)->first();

        if ($row === null) {
            $value = $fallbackConfigKey ? config($fallbackConfigKey) : $default;
            if ($value !== null) {
                Cache::put(self::CACHE_PREFIX.$key, (string) $value, self::CACHE_TTL);
            }
            return $value !== null ? (string) $value : null;
        }

        $value = $row->encrypted ? Crypt::decryptString((string) $row->value) : (string) $row->value;

        // Cache the decrypted value so subsequent reads skip the DB round-trip
        // AND the Crypt decode. Empty string cached as '' to distinguish from
        // a cache miss (null).
        Cache::put(self::CACHE_PREFIX.$key, $value, self::CACHE_TTL);

        return $value;
    }

    /**
     * Persist a single setting. Set encrypt=true for anything sensitive.
     * Invalidates the cache entry so the next read sees the fresh value.
     */
    public function set(string $key, ?string $value, string $group = 'general', bool $encrypt = false): void
    {
        $storedValue = ($value !== null && $encrypt)
            ? Crypt::encryptString($value)
            : $value;

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'encrypted' => $encrypt,
                'group' => $group,
            ],
        );

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Bulk save — used by the admin UI on form submit. Runs each set() in
     * turn (transactionally isn't necessary — a partial save is still valid,
     * unlike creating an employee where several rows go together).
     *
     * @param  array<int, array{key: string, value: ?string, group?: string, encrypt?: bool}>  $rows
     */
    public function setMany(array $rows): void
    {
        foreach ($rows as $row) {
            $this->set(
                $row['key'],
                $row['value'] ?? null,
                $row['group'] ?? 'general',
                $row['encrypt'] ?? false,
            );
        }
    }

    /**
     * All settings for a group, decrypted, keyed by short name (without the
     * group prefix). Missing keys aren't included — the admin UI merges its
     * own known-key list against this map to render blanks for unset values.
     *
     * @return array<string, string>
     */
    public function getGroup(string $group): array
    {
        $rows = Setting::query()->where('group', $group)->get();

        $result = [];
        foreach ($rows as $row) {
            $shortKey = str_starts_with($row->key, "{$group}.")
                ? substr($row->key, strlen($group) + 1)
                : $row->key;

            $result[$shortKey] = $row->encrypted
                ? Crypt::decryptString((string) $row->value)
                : (string) $row->value;
        }

        return $result;
    }

    /**
     * Wipe every cached settings entry — used on bulk imports or when a
     * migration adds new rows out-of-band.
     */
    public function flushCache(): void
    {
        // We don't track cached keys individually; a targeted Cache::forget
        // per known key is enough for the admin UI's normal flow. This method
        // exists for the rare 'something's wrong, force refresh' path.
        foreach (Setting::query()->pluck('key') as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }
}
