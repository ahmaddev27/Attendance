<?php

namespace App\Services;

use App\Repositories\SettingRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Keys whose values are encrypted at rest (e.g. third-party API credentials).
     * Stored ciphertext is transparently decrypted on read.
     *
     * @var list<string>
     */
    private const ENCRYPTED_KEYS = ['sms_password'];

    public function __construct(private readonly SettingRepository $repo) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();
        if (! isset($settings[$key])) {
            return $default;
        }

        $value = $settings[$key]['value'];
        $type = $settings[$key]['type'];

        if ($this->isEncrypted($key) && $value !== null && $value !== '') {
            $value = $this->tryDecrypt($value);
        }

        return $this->cast($value, $type);
    }

    public function set(string $key, mixed $value, string $type): void
    {
        $stored = match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        if ($this->isEncrypted($key) && $stored !== null && $stored !== '') {
            $stored = Crypt::encryptString($stored);
        }

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

    private function isEncrypted(string $key): bool
    {
        return in_array($key, self::ENCRYPTED_KEYS, true);
    }

    /**
     * Decrypts a stored value, tolerating legacy plaintext rows written
     * before encryption was introduced (returned as-is if decryption fails).
     */
    private function tryDecrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
}
