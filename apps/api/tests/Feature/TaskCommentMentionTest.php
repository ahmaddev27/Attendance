<?php

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\Notification;

function taskForMentionTests(): Task
{
    return Task::factory()->create([
        'status_id' => TaskStatus::factory(),
        'priority_id' => TaskPriority::factory(),
    ]);
}

/**
 * The mention picker sends employee ids and the request translates them to
 * the linked user ids, so the tests mention people exactly the way the UI
 * does.
 *
 * @param  array<string, mixed>  $userAttributes
 */
function mentionableEmployee(array $userAttributes = [], ?User $user = null): Employee
{
    $user ??= User::factory()->create($userAttributes);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $user->forceFill(['employee_id' => $employee->id])->save();

    return $employee->setRelation('user', $user);
}

test('everyone mentioned in a new comment is notified with a link to the task', function () {
    Notification::fake();
    actingAsAdmin();
    $task = taskForMentionTests();
    $first = mentionableEmployee();
    $second = mentionableEmployee();

    $this->postJson("/api/tasks/{$task->id}/comments", [
        'body' => 'Please check this.',
        'mentions' => [$first->id, $second->id],
    ])->assertCreated();

    foreach ([$first->user, $second->user] as $mentioned) {
        Notification::assertSentTo(
            $mentioned,
            TaqatNotification::class,
            fn (TaqatNotification $sent) => str_contains($sent->title, 'تمت الإشارة إليك')
                && $sent->url === "/my-tasks/{$task->id}",
        );
    }
});

test('the author is never notified about mentioning themselves', function () {
    Notification::fake();
    $author = actingAsAdmin();
    $task = taskForMentionTests();
    $authorEmployee = mentionableEmployee(user: $author);

    $this->postJson("/api/tasks/{$task->id}/comments", [
        'body' => 'Note to self.',
        'mentions' => [$authorEmployee->id],
    ])->assertCreated();

    Notification::assertNotSentTo($author, TaqatNotification::class);
});

test('inactive users are skipped and a comment without mentions notifies nobody', function () {
    Notification::fake();
    actingAsAdmin();
    $task = taskForMentionTests();
    $inactive = mentionableEmployee(['is_active' => false]);

    $this->postJson("/api/tasks/{$task->id}/comments", [
        'body' => 'Heads up.',
        'mentions' => [$inactive->id],
    ])->assertCreated();

    $this->postJson("/api/tasks/{$task->id}/comments", ['body' => 'No mentions here.'])->assertCreated();

    Notification::assertNothingSent();
});

test('a failing notifier never breaks posting the comment', function () {
    $notifier = $this->mock(NotificationService::class);
    $notifier->shouldIgnoreMissing();
    $notifier->shouldReceive('mentionedInComment')->andThrow(new RuntimeException('provider down'));

    actingAsAdmin();
    $task = taskForMentionTests();
    $mentioned = mentionableEmployee();

    $this->postJson("/api/tasks/{$task->id}/comments", [
        'body' => 'Still saved.',
        'mentions' => [$mentioned->id],
    ])->assertCreated();

    $this->assertDatabaseHas('task_comments', ['task_id' => $task->id, 'body' => 'Still saved.']);
});
