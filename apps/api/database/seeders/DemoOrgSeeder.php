<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Team;
use App\Modules\Employees\Services\EmployeeService;
use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\EmploymentType;
use App\Shared\Enums\Gender;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic demo organization for TAQAT: one company, four
 * departments, eight positions, three teams (under التطوير) and twelve
 * employees — with department managers and team leaders assigned.
 *
 * Idempotent: safe to re-run against a non-fresh database (matched by
 * natural keys), though the normal verification path is
 * `migrate:fresh --seed`.
 */
class DemoOrgSeeder extends Seeder
{
    public function run(EmployeeService $employeeService): void
    {
        $company = Company::query()->updateOrCreate(
            ['name' => 'TAQAT'],
            ['timezone' => 'Asia/Amman'],
        );

        $departments = $this->seedDepartments($company);
        $positions = $this->seedPositions($departments);
        $teams = $this->seedTeams($departments['dev']);

        $employeesByDepartment = $this->seedEmployees($employeeService, $departments, $positions, $teams);

        $this->assignDepartmentManagers($departments, $employeesByDepartment);
        $this->assignTeamLeaders($teams, $employeesByDepartment['dev']);
    }

    /**
     * @return array<string, Department>
     */
    private function seedDepartments(Company $company): array
    {
        $definitions = [
            'dev' => ['name' => 'التطوير', 'code' => 'DEV'],
            'hr' => ['name' => 'الموارد البشرية', 'code' => 'HR'],
            'sales' => ['name' => 'المبيعات', 'code' => 'SALES'],
            'admin' => ['name' => 'الإدارة العامة', 'code' => 'ADMIN'],
        ];

        $departments = [];

        foreach ($definitions as $key => $definition) {
            $departments[$key] = Department::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $definition['code']],
                ['name' => $definition['name'], 'is_active' => true],
            );
        }

        return $departments;
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, list<Position>>
     */
    private function seedPositions(array $departments): array
    {
        $definitions = [
            'dev' => ['مطور Backend', 'مطور Frontend'],
            'hr' => ['أخصائي موارد بشرية', 'مدير موارد بشرية'],
            'sales' => ['مندوب مبيعات', 'مدير مبيعات'],
            'admin' => ['منسق إداري', 'مدير عام'],
        ];

        $positions = [];

        foreach ($definitions as $departmentKey => $titles) {
            $positions[$departmentKey] = [];

            foreach ($titles as $title) {
                $positions[$departmentKey][] = Position::query()->updateOrCreate(
                    ['department_id' => $departments[$departmentKey]->id, 'title' => $title],
                    ['is_active' => true],
                );
            }
        }

        return $positions;
    }

    /**
     * @return array<string, Team>
     */
    private function seedTeams(Department $devDepartment): array
    {
        $teams = [];

        foreach (['Backend', 'Frontend', 'DevOps'] as $name) {
            $teams[$name] = Team::query()->updateOrCreate(
                ['department_id' => $devDepartment->id, 'name' => $name],
                ['is_active' => true],
            );
        }

        return $teams;
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, list<Position>>  $positions
     * @param  array<string, Team>  $teams
     * @return array<string, list<Employee>> employees grouped by department key
     */
    private function seedEmployees(
        EmployeeService $employeeService,
        array $departments,
        array $positions,
        array $teams,
    ): array {
        $teamCycle = array_values($teams);

        $roster = [
            'dev' => [
                ['first_name' => 'أحمد', 'last_name' => 'الطويل', 'gender' => Gender::Male],
                ['first_name' => 'سارة', 'last_name' => 'الحمد', 'gender' => Gender::Female],
                ['first_name' => 'خالد', 'last_name' => 'العتيبي', 'gender' => Gender::Male],
                ['first_name' => 'ليلى', 'last_name' => 'مراد', 'gender' => Gender::Female],
            ],
            'hr' => [
                ['first_name' => 'منى', 'last_name' => 'سالم', 'gender' => Gender::Female],
                ['first_name' => 'عمر', 'last_name' => 'حداد', 'gender' => Gender::Male],
                ['first_name' => 'ريم', 'last_name' => 'قاسم', 'gender' => Gender::Female],
            ],
            'sales' => [
                ['first_name' => 'يوسف', 'last_name' => 'نصار', 'gender' => Gender::Male],
                ['first_name' => 'هند', 'last_name' => 'الشريف', 'gender' => Gender::Female],
                ['first_name' => 'زياد', 'last_name' => 'كنعان', 'gender' => Gender::Male],
            ],
            'admin' => [
                ['first_name' => 'نور', 'last_name' => 'العلي', 'gender' => Gender::Female],
                ['first_name' => 'طارق', 'last_name' => 'فارس', 'gender' => Gender::Male],
            ],
        ];

        $employeesByDepartment = [];
        $sequence = 0;

        foreach ($roster as $departmentKey => $people) {
            $employeesByDepartment[$departmentKey] = [];

            foreach ($people as $index => $person) {
                $sequence++;
                $email = sprintf('demo.employee%02d@taqat.local', $sequence);

                $employee = Employee::query()->where('email', $email)->first();

                if ($employee === null) {
                    $employee = $employeeService->create([
                        'first_name' => $person['first_name'],
                        'last_name' => $person['last_name'],
                        'email' => $email,
                        'phone' => sprintf('079%07d', 1000000 + $sequence),
                        'department_id' => $departments[$departmentKey]->id,
                        'position_id' => $positions[$departmentKey][$index % 2]->id,
                        'team_id' => $departmentKey === 'dev' ? $teamCycle[$index % count($teamCycle)]->id : null,
                        'employment_type' => EmploymentType::FullTime,
                        'joining_date' => now()->subMonths(random_int(1, 36))->toDateString(),
                        'gender' => $person['gender'],
                        'status' => EmployeeStatus::Active,
                    ]);
                }

                $employeesByDepartment[$departmentKey][] = $employee;
            }
        }

        return $employeesByDepartment;
    }

    /**
     * Designate the first employee of each department as its manager.
     *
     * @param  array<string, Department>  $departments
     * @param  array<string, list<Employee>>  $employeesByDepartment
     */
    private function assignDepartmentManagers(array $departments, array $employeesByDepartment): void
    {
        foreach ($departments as $key => $department) {
            $manager = $employeesByDepartment[$key][0] ?? null;

            if ($manager !== null && $department->manager_id !== $manager->id) {
                $department->update(['manager_id' => $manager->id]);
            }
        }
    }

    /**
     * Designate the first employee assigned to each dev team as its leader.
     *
     * @param  array<string, Team>  $teams
     * @param  list<Employee>  $devEmployees
     */
    private function assignTeamLeaders(array $teams, array $devEmployees): void
    {
        $teamCycle = array_values($teams);

        foreach ($teamCycle as $index => $team) {
            $leader = $devEmployees[$index] ?? null;

            if ($leader !== null && $team->leader_id !== $leader->id) {
                $team->update(['leader_id' => $leader->id]);
            }
        }
    }
}
