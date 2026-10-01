<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Seeds three demonstration companies for the Companies admin surface
 * (طاقات with Asia/Amman, BrightGaza with Asia/Gaza, شركة تجريبية with
 * Asia/Dubai). The first two get a placeholder logo attached via the
 * factory's withLogo() state so the admin UI can render the `logo_url`
 * badge end-to-end without a manual upload step.
 *
 * Each company is given two shallow departments (names only, no deeper
 * org data) — that's enough to populate the "X قسم" counter on the card
 * and the tree view, while leaving the richer org fixtures to
 * DemoOrgSeeder.
 *
 * Idempotent: companies are matched by name (firstOrCreate), departments
 * by their (globally unique) code, so repeat runs are safe. Guarded by
 * the same ALLOW_DEMO_SEED env the sibling demo seeders check — the
 * DatabaseSeeder gate already enforces this, this inner guard just
 * protects direct `db:seed --class=` calls on production.
 */
class CompaniesDemoSeeder extends Seeder
{
    /**
     * @var list<array{name: string, timezone: string, with_logo: bool, departments: list<array{name: string, code: string}>}>
     */
    private const COMPANY_DEFINITIONS = [
        [
            'name' => 'طاقات',
            'timezone' => 'Asia/Amman',
            'with_logo' => true,
            'departments' => [
                ['name' => 'الهندسة', 'code' => 'DEMO-TAQAT-ENG'],
                ['name' => 'الموارد البشرية', 'code' => 'DEMO-TAQAT-HR'],
            ],
        ],
        [
            'name' => 'BrightGaza',
            'timezone' => 'Asia/Gaza',
            'with_logo' => true,
            'departments' => [
                ['name' => 'Engineering', 'code' => 'DEMO-BG-ENG'],
                ['name' => 'Operations', 'code' => 'DEMO-BG-OPS'],
            ],
        ],
        [
            'name' => 'شركة تجريبية',
            'timezone' => 'Asia/Dubai',
            'with_logo' => false,
            'departments' => [
                ['name' => 'المبيعات', 'code' => 'DEMO-SMPL-SALES'],
                ['name' => 'المالية', 'code' => 'DEMO-SMPL-FIN'],
            ],
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production') && ! env('ALLOW_DEMO_SEED')) {
            $this->command?->warn(
                'Skipping CompaniesDemoSeeder in production. Set ALLOW_DEMO_SEED=true to override.',
            );

            return;
        }

        foreach (self::COMPANY_DEFINITIONS as $definition) {
            $company = $this->seedCompany(
                $definition['name'],
                $definition['timezone'],
                $definition['with_logo'],
            );

            $this->seedDepartments($company, $definition['departments']);
        }
    }

    private function seedCompany(string $name, string $timezone, bool $withLogo): Company
    {
        $existing = Company::query()->where('name', $name)->first();

        if ($existing !== null) {
            // Keep the timezone fresh on re-runs but leave an existing
            // logo in place — overwriting it on every seed would litter
            // the public disk with orphaned factory PNGs.
            if ($existing->timezone !== $timezone) {
                $existing->update(['timezone' => $timezone]);
            }

            return $existing;
        }

        $factory = Company::factory();

        if ($withLogo) {
            $factory = $factory->withLogo();
        }

        return $factory->create([
            'name' => $name,
            'timezone' => $timezone,
        ]);
    }

    /**
     * @param  list<array{name: string, code: string}>  $departments
     */
    private function seedDepartments(Company $company, array $departments): void
    {
        foreach ($departments as $department) {
            Department::query()->updateOrCreate(
                ['code' => $department['code']],
                [
                    'company_id' => $company->id,
                    'name' => $department['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
