<?php

use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * MediaLibrary's disk_name defaults to 'public' in this app (no MEDIA_DISK
 * override is set for the testing environment) — MinIO is not reachable
 * from the test suite, so every test here fakes that disk instead.
 */
beforeEach(function () {
    Storage::fake('public');
});

function makeTaskForAttachments(): Task
{
    return Task::factory()->create([
        'status_id' => TaskStatus::factory(),
        'priority_id' => TaskPriority::factory(),
    ]);
}

test('attachment endpoints require authentication', function () {
    $task = makeTaskForAttachments();

    $this->getJson("/api/tasks/{$task->id}/attachments")->assertUnauthorized();
});

test('a user can upload an attachment to a task', function () {
    actingAsAdmin();
    $task = makeTaskForAttachments();
    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

    $response = $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => $file]);

    $response->assertCreated()->assertJsonPath('data.file_name', 'document.pdf');

    $this->assertDatabaseHas('media', [
        'model_type' => Task::class,
        'model_id' => $task->id,
        'collection_name' => 'attachments',
        'file_name' => 'document.pdf',
    ]);

    expect(TaskHistory::query()->where('task_id', $task->id)->where('action', 'attached_file')->exists())->toBeTrue();
});

test('uploading a file over the size limit is rejected', function () {
    actingAsAdmin();
    $task = makeTaskForAttachments();
    $tooLarge = UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'); // just over 10MB

    $response = $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => $tooLarge]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['file']);
});

test('uploading a disallowed file type is rejected', function () {
    actingAsAdmin();
    $task = makeTaskForAttachments();
    $disallowed = UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream');

    $response = $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => $disallowed]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['file']);
});

test('an uploaded attachment can be downloaded through its signed url', function () {
    actingAsAdmin();
    $task = makeTaskForAttachments();
    $file = UploadedFile::fake()->create('report.pdf', 200, 'application/pdf');

    $uploadResponse = $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => $file]);
    $downloadUrl = $uploadResponse->json('data.download_url');

    expect($downloadUrl)->not->toBeNull();

    $downloadResponse = $this->get($downloadUrl);

    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('content-disposition');
});

test('a download link without a valid signature is rejected', function () {
    actingAsAdmin();
    $task = makeTaskForAttachments();
    $file = UploadedFile::fake()->create('report.pdf', 200, 'application/pdf');

    $uploadResponse = $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => $file]);
    $mediaId = $uploadResponse->json('data.id');

    $this->getJson("/api/attachments/{$mediaId}/download")->assertForbidden();
});

test('a user can delete an attachment', function () {
    actingAsAdmin();
    $task = makeTaskForAttachments();
    $file = UploadedFile::fake()->create('temp.pdf', 100, 'application/pdf');

    $uploadResponse = $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => $file]);
    $mediaId = $uploadResponse->json('data.id');

    $this->deleteJson("/api/attachments/{$mediaId}")->assertNoContent();

    $this->assertDatabaseMissing('media', ['id' => $mediaId]);
});

test('an admin can list a task\'s attachments', function () {
    actingAsAdmin();
    $task = makeTaskForAttachments();
    $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => UploadedFile::fake()->create('one.pdf', 100, 'application/pdf')]);
    $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => UploadedFile::fake()->create('two.pdf', 100, 'application/pdf')]);

    $response = $this->getJson("/api/tasks/{$task->id}/attachments");

    $response->assertOk()->assertJsonCount(2, 'data');
});
