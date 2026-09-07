<?php

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskHistory;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;

function makeTaskForComments(): Task
{
    return Task::factory()->create([
        'status_id' => TaskStatus::factory(),
        'priority_id' => TaskPriority::factory(),
    ]);
}

test('task comments endpoints require authentication', function () {
    $task = makeTaskForComments();

    $this->getJson("/api/tasks/{$task->id}/comments")->assertUnauthorized();
});

test('a user can create a comment on a task', function () {
    actingAsAdmin();
    $task = makeTaskForComments();

    $response = $this->postJson("/api/tasks/{$task->id}/comments", [
        'body' => 'Looks good to me.',
    ]);

    $response->assertCreated()->assertJsonPath('data.body', 'Looks good to me.');

    $this->assertDatabaseHas('task_comments', ['task_id' => $task->id, 'body' => 'Looks good to me.']);

    expect(TaskHistory::query()->where('task_id', $task->id)->where('action', 'commented')->exists())->toBeTrue();
});

test('mentions are stored as an array of user ids on the comment', function () {
    actingAsAdmin();
    $task = makeTaskForComments();
    $mentioned = User::factory()->count(2)->create();

    $response = $this->postJson("/api/tasks/{$task->id}/comments", [
        'body' => 'Please review @mentions.',
        'mentions' => $mentioned->pluck('id')->all(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.mentions', $mentioned->pluck('id')->all());

    $comment = TaskComment::query()->findOrFail($response->json('data.id'));
    expect($comment->mentions)->toBe($mentioned->pluck('id')->all());
});

test('a reply comment nests under its parent when the thread is listed', function () {
    actingAsAdmin();
    $task = makeTaskForComments();

    $root = TaskComment::factory()->create(['task_id' => $task->id, 'body' => 'Root comment']);
    $reply = TaskComment::factory()->replyTo($root)->create(['body' => 'A reply']);

    $response = $this->getJson("/api/tasks/{$task->id}/comments");

    $response->assertOk()->assertJsonCount(1, 'data'); // one top-level comment
    expect($response->json('data.0.id'))->toBe($root->id);
    expect($response->json('data.0.replies.0.id'))->toBe($reply->id);
});

test('comments are listed newest top-level comment first', function () {
    actingAsAdmin();
    $task = makeTaskForComments();

    $older = TaskComment::factory()->create(['task_id' => $task->id, 'created_at' => now()->subHour()]);
    $newer = TaskComment::factory()->create(['task_id' => $task->id, 'created_at' => now()]);

    $response = $this->getJson("/api/tasks/{$task->id}/comments");

    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($newer->id);
    expect($response->json('data.1.id'))->toBe($older->id);
});

test('a user can update their own comment and edited_at is stamped', function () {
    $user = actingAsAdmin();
    $comment = TaskComment::factory()->create(['user_id' => $user->id, 'body' => 'Original']);

    $response = $this->putJson("/api/comments/{$comment->id}", ['body' => 'Edited body']);

    $response->assertOk()
        ->assertJsonPath('data.body', 'Edited body')
        ->assertJsonPath('data.edited_at', fn ($value) => $value !== null);

    $comment->refresh();
    expect($comment->body)->toBe('Edited body');
    expect($comment->edited_at)->not->toBeNull();
});

test('a user cannot update another user\'s comment', function () {
    $owner = User::factory()->create();
    $comment = TaskComment::factory()->create(['user_id' => $owner->id]);

    actingAsAdmin(); // a different user

    $response = $this->putJson("/api/comments/{$comment->id}", ['body' => 'Hijacked']);

    $response->assertForbidden();
});

test('a user cannot delete another user\'s comment', function () {
    $owner = User::factory()->create();
    $comment = TaskComment::factory()->create(['user_id' => $owner->id]);

    actingAsAdmin();

    $this->deleteJson("/api/comments/{$comment->id}")->assertForbidden();
    $this->assertDatabaseHas('task_comments', ['id' => $comment->id, 'deleted_at' => null]);
});

test('a user can delete their own comment', function () {
    $user = actingAsAdmin();
    $comment = TaskComment::factory()->create(['user_id' => $user->id]);

    $this->deleteJson("/api/comments/{$comment->id}")->assertNoContent();

    $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
});
