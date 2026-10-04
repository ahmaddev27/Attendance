<?php

declare(strict_types=1);

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateImportJob;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Modules\Recruitment\Events\CsvImportCompleted;
use App\Modules\Recruitment\Jobs\ProcessCandidateCsvImport;
use App\Modules\Recruitment\Services\CandidateImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * Local mirror of makeJobForShortlist from CandidateShortlistTest —
 * Pest keeps top-level functions file-scoped, so each file redeclares.
 * `$instance` is untyped on purpose: Pest proxies $this to a P\…
 * subclass that fails a trait typehint even though the trait IS mixed
 * in. See the note on makeJobOnStandardPipeline in CandidateApplicationTest.
 */
function makeJobForImport($instance): JobRequirement
{
    $pipeline = $instance->seedStandardPipeline();
    $firstStage = $pipeline->stages()->orderBy('display_order')->first();

    $owner = User::factory()->create();
    $client = Client::create([
        'client_number' => 'C-'.date('Y').'-'.random_int(1000, 9999),
        'company_name' => 'ACME '.random_int(100, 999),
        'country' => 'Palestine',
        'city' => 'Gaza',
        'owner_id' => $owner->id,
    ]);
    $case = RecruitmentCase::create([
        'case_number' => 'RC-'.date('Y').'-'.random_int(1000, 9999),
        'client_id' => $client->id,
        'title' => 'Hiring Drive '.random_int(100, 999),
        'owner_id' => $owner->id,
    ]);

    return JobRequirement::create([
        'job_number' => 'J-'.date('Y').'-'.random_int(1000, 9999),
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $firstStage->id,
        'owner_id' => $owner->id,
        'title' => 'Senior Backend',
        'employment_type' => 'full_time',
        'work_mode' => 'onsite',
        'openings' => 2,
        'status' => 'active',
        'stage_entered_at' => now(),
    ]);
}

/**
 * Build an UploadedFile from an array of header-less rows. The caller
 * passes raw CSV lines so a test can see exactly what the import sees;
 * the template header is prepended here so tests stay compact.
 *
 * @param  list<string>  $dataLines
 */
function makeCandidateCsv(array $dataLines, string $filename = 'candidates.csv'): UploadedFile
{
    $header = implode(',', CandidateImportService::TEMPLATE_HEADERS);
    $contents = $header."\n".implode("\n", $dataLines)."\n";

    return UploadedFile::fake()->createWithContent($filename, $contents);
}

test('template endpoint streams the fixed header row as a CSV', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobForImport($this);

    $response = $this->get("/api/jobs/{$job->id}/applications/import/template")->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    // The streamed body includes the UTF-8 BOM + the comma-joined header.
    $body = $response->streamedContent();
    expect($body)->toContain('full_name,email,phone');
});

test('dry-run returns parsed + errors without writing a single row', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobForImport($this);

    $file = makeCandidateCsv([
        'Ahmad Dev,ahmad@example.com,+970599000111,Palestine,Gaza,,,,,,,,,,,,',
        'Bad Row,not-an-email,,,,,,,,,,,,,,,',
    ]);

    $response = $this
        ->post("/api/jobs/{$job->id}/applications/import/dry-run", ['file' => $file])
        ->assertOk();

    expect($response->json('data.parsed'))->toBe(2);
    expect($response->json('data.errors'))->not->toBeEmpty();
    expect($response->json('data.preview'))->toHaveCount(2);
    expect(Candidate::count())->toBe(0);
    expect(CandidateApplication::count())->toBe(0);
});

test('store persists the file, mints a Pending job row and queues the worker', function () {
    Storage::fake('local');
    Queue::fake();

    $this->actingAsRecruitmentAdmin();
    $job = makeJobForImport($this);

    $file = makeCandidateCsv([
        'Ahmad Dev,ahmad@example.com,+970599000111,,,,,,,,,,,,,,',
    ]);

    $response = $this
        ->post("/api/jobs/{$job->id}/applications/import", ['file' => $file])
        ->assertCreated();

    expect($response->json('data.status'))->toBe('pending');
    expect($response->json('data.job_requirement_id'))->toBe($job->id);

    $importJob = CandidateImportJob::firstOrFail();
    expect($importJob->uploaded_filename)->toBe('candidates.csv');

    Queue::assertPushed(ProcessCandidateCsvImport::class, function (ProcessCandidateCsvImport $queued) use ($importJob): bool {
        return $queued->importJobId === $importJob->id;
    });

    // Storage::fake uses a real temp local disk — the stored file exists.
    Storage::disk('local')->assertExists($importJob->storage_path);
});

test('end-to-end: happy path creates candidates, applications, and flips to completed', function () {
    Storage::fake('local');
    Queue::fake();
    Event::fake([CsvImportCompleted::class]);

    $admin = $this->actingAsRecruitmentAdmin();
    $job = makeJobForImport($this);

    $file = makeCandidateCsv([
        'Ahmad Dev,ahmad@example.com,+970599000111,Palestine,Gaza,,,,,,,,,,PHP|Laravel,English|Arabic,',
        'Lina Dev,lina@example.com,+970599000222,Palestine,Khan Yunis,,,,,,,,,,,,',
    ]);

    $this->post("/api/jobs/{$job->id}/applications/import", ['file' => $file])->assertCreated();

    // Manually drive the worker — Queue::fake blocked the automatic run.
    $importJob = CandidateImportJob::firstOrFail();
    (new ProcessCandidateCsvImport($importJob->id))->handle(app(CandidateImportService::class));

    $importJob->refresh();
    expect($importJob->status->value)->toBe('completed_with_errors');
    expect($importJob->total_rows)->toBe(2);
    expect($importJob->errors)->toBe([]);
    expect($importJob->created_candidates)->toBe(2);
    expect($importJob->created_applications)->toBe(2);

    $candidate = Candidate::firstWhere('email', 'ahmad@example.com');
    expect($candidate)->not->toBeNull();
    expect($candidate->source)->toBe('csv_import');
    expect($candidate->skills)->toBe(['PHP', 'Laravel']);
    expect(CandidateApplication::where('job_requirement_id', $job->id)->count())->toBe(2);

    Event::assertDispatched(CsvImportCompleted::class, fn ($event) => $event->importJob->id === $importJob->id
        && $event->uploader->id === $admin->id);
});

test('end-to-end: dedup reuses the existing candidate silently', function () {
    Storage::fake('local');
    Queue::fake();

    $this->actingAsRecruitmentAdmin();
    $job = makeJobForImport($this);

    // Pre-existing candidate — email on the row below MUST match.
    $existing = Candidate::factory()->create([
        'email' => 'ahmad@example.com',
        'phone' => '+970599000111',
    ]);

    $file = makeCandidateCsv([
        'Ahmad Dev,ahmad@example.com,+970599000111,,,,,,,,,,,,,,',
    ]);

    $this->post("/api/jobs/{$job->id}/applications/import", ['file' => $file])->assertCreated();

    $importJob = CandidateImportJob::firstOrFail();
    (new ProcessCandidateCsvImport($importJob->id))->handle(app(CandidateImportService::class));

    $importJob->refresh();
    expect($importJob->reused_candidates)->toBe(1);
    expect($importJob->created_candidates)->toBe(0);
    expect($importJob->created_applications)->toBe(1);

    // Only one candidate row — the dedup prevented a second insert.
    expect(Candidate::where('email', 'ahmad@example.com')->count())->toBe(1);
    expect(CandidateApplication::where('candidate_id', $existing->id)->where('job_requirement_id', $job->id)->exists())->toBeTrue();
});

test('end-to-end: already-applied duplicate is skipped with a warning', function () {
    Storage::fake('local');
    Queue::fake();

    $this->actingAsRecruitmentAdmin();
    $job = makeJobForImport($this);

    $candidate = Candidate::factory()->create([
        'email' => 'dup@example.com',
        'phone' => '+970599999999',
    ]);
    // Candidate already on the job — the import must detect this.
    CandidateApplication::factory()->create([
        'candidate_id' => $candidate->id,
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $file = makeCandidateCsv([
        'Dup Candidate,dup@example.com,+970599999999,,,,,,,,,,,,,,',
    ]);

    $this->post("/api/jobs/{$job->id}/applications/import", ['file' => $file])->assertCreated();

    $importJob = CandidateImportJob::firstOrFail();
    (new ProcessCandidateCsvImport($importJob->id))->handle(app(CandidateImportService::class));

    $importJob->refresh();
    expect($importJob->skipped_duplicates)->toBe(1);
    expect($importJob->created_applications)->toBe(0);
    expect($importJob->reused_candidates)->toBe(1);
    expect($importJob->errors)->not->toBeEmpty();
    expect($importJob->errors[0]['field'])->toBe('candidate_id');

    // No duplicate row — the UNIQUE guard kicked in.
    expect(CandidateApplication::where('candidate_id', $candidate->id)->count())->toBe(1);
});

test('store rejects an unsupported mime type with 422', function () {
    $this->withHeader('Accept', 'application/json');
    Storage::fake('local');
    Queue::fake();

    $this->actingAsRecruitmentAdmin();
    $job = makeJobForImport($this);

    $file = UploadedFile::fake()->create('candidates.pdf', 10, 'application/pdf');

    $this->post("/api/jobs/{$job->id}/applications/import", ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    Queue::assertNothingPushed();
    expect(CandidateImportJob::count())->toBe(0);
});

test('store rejects a file bigger than 5MB with 422', function () {
    $this->withHeader('Accept', 'application/json');
    Storage::fake('local');
    Queue::fake();

    $this->actingAsRecruitmentAdmin();
    $job = makeJobForImport($this);

    // 6 MB — one above the plan's 5 MB cap.
    $file = UploadedFile::fake()->create('candidates.csv', 6 * 1024, 'text/csv');

    $this->post("/api/jobs/{$job->id}/applications/import", ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    Queue::assertNothingPushed();
});

test('status endpoint returns progress and 404s when the job belongs to a different requirement', function () {
    Storage::fake('local');

    $this->actingAsRecruitmentAdmin();
    $jobA = makeJobForImport($this);
    $jobB = makeJobForImport($this);

    $importJob = CandidateImportJob::factory()->create([
        'job_requirement_id' => $jobA->id,
        'status' => 'processing',
        'total_rows' => 25,
        'created_candidates' => 10,
    ]);

    // Correct scope — returns the row.
    $this->getJson("/api/jobs/{$jobA->id}/applications/import/{$importJob->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.total_rows', 25);

    // Wrong scope — must not leak across jobs.
    $this->getJson("/api/jobs/{$jobB->id}/applications/import/{$importJob->id}")
        ->assertNotFound();
});

test('view-candidates alone is not enough — the import gate is manage-candidates', function () {
    Storage::fake('local');

    $this->actingAsUserWithPermissions(['view-jobs', 'view-candidates']);
    $job = makeJobForImport($this);

    $file = makeCandidateCsv([
        'Ahmad Dev,ahmad@example.com,+970599000111,,,,,,,,,,,,,,',
    ]);

    $this->get("/api/jobs/{$job->id}/applications/import/template")->assertForbidden();
    $this->post("/api/jobs/{$job->id}/applications/import/dry-run", ['file' => $file])->assertForbidden();
    $this->post("/api/jobs/{$job->id}/applications/import", ['file' => $file])->assertForbidden();
});
