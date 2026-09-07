<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskTag;
use App\Models\User;
use App\Modules\Tasks\Services\TaskCommentService;
use App\Modules\Tasks\Services\TaskService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * NOTE FOR THE COORDINATOR: this class is intentionally not wired into
 * DatabaseSeeder::run() — per the M6 brief, that file is left for the
 * coordinator to edit. Add `TaskSeeder::class` to its $this->call([])
 * list, after DemoOrgSeeder (the sample tasks need the 12 seeded
 * employees to already exist). Until then, run it explicitly:
 * `php artisan db:seed --class=TaskSeeder`.
 */
class TaskSeeder extends Seeder
{
    public function run(TaskService $tasks, TaskCommentService $comments): void
    {
        $this->seedTaskStatuses();
        $this->seedTaskPriorities();
        $this->seedTaskTags();

        if (! Schema::hasTable('employees')) {
            return;
        }

        $employees = Employee::query()->orderBy('id')->get();

        if ($employees->isEmpty()) {
            return;
        }

        $this->seedSampleTasksWithComments($tasks, $comments, $employees);
    }

    private function seedTaskStatuses(): void
    {
        $statuses = [
            ['code' => 'backlog', 'name' => 'قيد المراجعة', 'sort_order' => 1, 'color' => '#7C8698'],
            ['code' => 'todo', 'name' => 'للتنفيذ', 'sort_order' => 2, 'color' => '#2678C4'],
            ['code' => 'in_progress', 'name' => 'قيد التنفيذ', 'sort_order' => 3, 'color' => '#F5A623'],
            ['code' => 'done', 'name' => 'مكتملة', 'sort_order' => 4, 'color' => '#3FA34D', 'is_done_state' => true],
            ['code' => 'cancelled', 'name' => 'ملغاة', 'sort_order' => 5, 'color' => '#C74F35', 'is_cancelled_state' => true],
        ];

        foreach ($statuses as $status) {
            TaskStatus::query()->updateOrCreate(['code' => $status['code']], [
                ...$status,
                'is_done_state' => $status['is_done_state'] ?? false,
                'is_cancelled_state' => $status['is_cancelled_state'] ?? false,
            ]);
        }
    }

    private function seedTaskPriorities(): void
    {
        $priorities = [
            ['code' => 'low', 'name' => 'منخفض', 'sort_order' => 1, 'color' => '#7C8698'],
            ['code' => 'medium', 'name' => 'متوسط', 'sort_order' => 2, 'color' => '#2678C4'],
            ['code' => 'high', 'name' => 'عالي', 'sort_order' => 3, 'color' => '#F5A623'],
            ['code' => 'urgent', 'name' => 'عاجل', 'sort_order' => 4, 'color' => '#C74F35'],
        ];

        foreach ($priorities as $priority) {
            TaskPriority::query()->updateOrCreate(['code' => $priority['code']], $priority);
        }
    }

    private function seedTaskTags(): void
    {
        $tags = [
            ['name' => 'تطوير', 'color' => '#2678C4'],
            ['name' => 'اختبار', 'color' => '#F5A623'],
            ['name' => 'بحث', 'color' => '#7C8698'],
            ['name' => 'توثيق', 'color' => '#3FA34D'],
            ['name' => 'اجتماع', 'color' => '#9B59B6'],
            ['name' => 'متابعة', 'color' => '#C74F35'],
        ];

        foreach ($tags as $tag) {
            TaskTag::query()->updateOrCreate(['name' => $tag['name']], $tag);
        }
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedSampleTasksWithComments(TaskService $tasks, TaskCommentService $comments, Collection $employees): void
    {
        $priorities = TaskPriority::query()->ordered()->get();
        $statuses = TaskStatus::query()->ordered()->get();
        $tags = TaskTag::query()->get();

        $titles = [
            'تجهيز بيئة التطوير الجديدة',
            'إصلاح خلل تسجيل الدخول',
            'مراجعة تصميم واجهة الموظفين',
            'كتابة توثيق واجهة برمجة التطبيقات',
            'اختبار وحدة الحضور والانصراف',
            'تحديث مكتبات الواجهة الأمامية',
            'تحسين أداء استعلامات التقارير',
            'إعداد بيئة الإنتاج الجديدة',
            'مراجعة طلبات الإجازات المعلقة',
            'متابعة تنفيذ خطة الربع الحالي',
        ];

        /** @var array<int, Task> $createdTasks */
        $createdTasks = [];

        foreach ($titles as $index => $title) {
            $creator = $employees[$index % $employees->count()];
            $assignee = $employees[($index + 3) % $employees->count()];
            $status = $statuses[$index % $statuses->count()];
            $priority = $priorities[$index % $priorities->count()];

            $dueDate = match (true) {
                $index % 3 === 0 => now()->subDays(2)->toDateString(),  // overdue
                $index % 3 === 1 => now()->addWeek()->toDateString(),   // upcoming
                default => null,                                        // no due date
            };

            $task = $tasks->create([
                'title' => $title,
                'description' => "وصف تفصيلي للمهمة: {$title}.",
                'status_id' => $status->id,
                'priority_id' => $priority->id,
                'created_by' => $creator->id,
                'assigned_to' => $index % 5 === 0 ? null : $assignee->id, // a couple left unassigned
                'estimated_hours' => (float) (2 + $index),
                'due_date' => $dueDate,
                'progress_percent' => $status->is_done_state ? 100 : ($index * 7) % 90,
                'tags' => [$tags[$index % $tags->count()]->id],
            ], $this->userFor($creator));

            $createdTasks[] = $task;

            $commenters = [$creator, $assignee, $employees[($index + 7) % $employees->count()]];

            foreach ($commenters as $commentIndex => $commenter) {
                $comments->create(
                    $task,
                    $this->userFor($commenter),
                    "تعليق رقم ".($commentIndex + 1)." على مهمة \"{$title}\".",
                );
            }
        }

        // One demo subtask, to exercise parent_task_id in a fresh seed.
        if (isset($createdTasks[0])) {
            $tasks->create([
                'parent_task_id' => $createdTasks[0]->id,
                'title' => 'مهمة فرعية: '.$createdTasks[0]->title,
                'status_id' => $statuses->first()->id,
                'priority_id' => $priorities->first()->id,
                'created_by' => $createdTasks[0]->created_by,
            ], $this->userFor($employees->firstWhere('id', $createdTasks[0]->created_by)));
        }
    }

    /**
     * Finds or creates the User account backing an Employee's demo
     * activity (task creation, assignment, comments). DemoOrgSeeder does
     * not itself create login accounts for the employees it seeds, but
     * TaskHistory/TaskComment both need a real `users` row as the actor —
     * so this fills that gap idempotently rather than depending on
     * DemoOrgSeeder to do it.
     */
    private function userFor(Employee $employee): User
    {
        $user = User::query()->firstOrCreate(
            ['employee_id' => $employee->id],
            [
                'employee_number' => $employee->employee_number,
                'name' => $employee->full_name,
                'email' => $employee->email ?? Str::slug($employee->full_name).'.'.$employee->id.'@taqat.local',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        if ($employee->user_id === null) {
            $employee->update(['user_id' => $user->id]);
        }

        return $user;
    }
}
