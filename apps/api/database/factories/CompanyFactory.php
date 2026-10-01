<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * 1x1 transparent PNG used as the placeholder for the withLogo() state.
     * Kept inline so the factory has zero fixture-file dependencies — the
     * same callback works in CI, in SQLite in-memory tests with the faked
     * `public` disk, and in local demo seeds against the real disk.
     */
    private const PLACEHOLDER_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * Directory on the `public` disk where real logos land (see
     * CompanyService::LOGO_DIRECTORY). Mirrored here so a factory-made
     * logo lives alongside production uploads.
     */
    private const LOGO_DIRECTORY = 'company-logos';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'logo_path' => null,
            'timezone' => 'Asia/Amman',
            'settings' => null,
        ];
    }

    /**
     * Attach a real placeholder image to Storage::disk('public') so the
     * resulting Company carries a resolvable `logo_url`. The write happens
     * in afterMaking so it fires once per instance (afterCreating would
     * also work, but then calling ->make() would never produce the file;
     * tests that only `make()` a Company still get a consistent artifact).
     *
     * Test-safe: when `Storage::fake('public')` is active the write lands
     * on the in-memory fake disk and never touches real storage.
     */
    public function withLogo(): static
    {
        return $this->afterMaking(function (Company $company) {
            $path = self::LOGO_DIRECTORY.'/factory-'.Str::uuid()->toString().'.png';

            Storage::disk('public')->put(
                $path,
                base64_decode(self::PLACEHOLDER_PNG_BASE64, true),
            );

            $company->logo_path = $path;
        });
    }
}
