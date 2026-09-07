<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Modules\Leaves\Services\LeaveBalanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * NOTE FOR THE COORDINATOR: this class is intentionally not wired into
 * DatabaseSeeder::run() — per the M4 brief, that file is left for the
 * coordinator to edit. Add `LeaveSeeder::class` to its $this->call([])
 * list, after DemoOrgSeeder (balance accrual needs the 12 seeded
 * employees to already exist). Until then, run it explicitly:
 * `php artisan db:seed --class=LeaveSeeder`.
 */
class LeaveSeeder extends Seeder
{
    public function run(LeaveBalanceService $balances): void
    {
        $this->seedLeaveTypes();

        if (! Schema::hasTable('employees')) {
            return;
        }

        $this->accrueCurrentYearBalances($balances);
    }

    private function seedLeaveTypes(): void
    {
        $types = [
            [
                'code' => 'annual',
                'name' => 'الإجازة السنوية',
                'is_paid' => true,
                'is_balance_based' => true,
                'default_annual_entitlement' => 21.00,
                'allow_negative_balance' => false,
                'requires_attachment' => false,
                'min_notice_days' => 7,
                'color' => '#2678C4',
                'sort_order' => 1,
            ],
            [
                'code' => 'sick',
                'name' => 'الإجازة المرضية',
                'is_paid' => true,
                'is_balance_based' => true,
                'default_annual_entitlement' => 14.00,
                'allow_negative_balance' => false,
                // Only leaves longer than 3 days need a supporting
                // document in the source policy — M4 has no per-request
                // day-count threshold on this flag yet, so it is applied
                // at the type level; a future milestone can refine it to a
                // conditional check inside LeaveRequestService::submit().
                'requires_attachment' => true,
                'min_notice_days' => 0,
                'color' => '#F5A623',
                'sort_order' => 2,
            ],
            [
                'code' => 'emergency',
                'name' => 'الإجازة الطارئة',
                'is_paid' => true,
                'is_balance_based' => false,
                'default_annual_entitlement' => 0,
                'allow_negative_balance' => false,
                'requires_attachment' => false,
                'min_notice_days' => 0,
                'color' => '#C74F35',
                'sort_order' => 3,
            ],
            [
                'code' => 'unpaid',
                'name' => 'بدون راتب',
                'is_paid' => false,
                'is_balance_based' => false,
                'default_annual_entitlement' => 0,
                'allow_negative_balance' => false,
                'requires_attachment' => false,
                'min_notice_days' => 0,
                'color' => '#7C8698',
                'sort_order' => 4,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::query()->updateOrCreate(['code' => $type['code']], $type);
        }
    }

    /**
     * accrueForYear() only ever creates rows for active, balance-based
     * types — of the four seeded above that is exactly annual + sick — so
     * every employee ends up with both without this seeder needing to
     * name them explicitly.
     */
    private function accrueCurrentYearBalances(LeaveBalanceService $balances): void
    {
        $year = (int) now()->year;

        Employee::query()->each(function (Employee $employee) use ($balances, $year) {
            $balances->accrueForYear($employee, $year);
        });
    }
}
