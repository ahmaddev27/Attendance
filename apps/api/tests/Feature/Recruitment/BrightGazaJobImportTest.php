<?php

use App\Models\Client;
use App\Models\JobRequirement;
use App\Modules\Recruitment\Repositories\JobRequirementRepository;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * Shape copied from BrightGaza's public GET /api/v1/jobs (2026-09-14).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function brightGazaListing(int $id, array $overrides = []): array
{
    return [
        'id' => $id,
        'title' => "Remote Laravel Developer #{$id}",
        'description' => '<p><strong>Work Type:</strong> Remote<br>Long-term</p><ul><li>PHP</li><li>Laravel</li></ul>',
        'category' => ['id' => 1, 'name' => 'Development & IT'],
        'sub_category' => ['id' => 4, 'name' => 'Full-Stack'],
        'skills' => [['id' => 7, 'name' => 'Laravel'], ['id' => 9, 'name' => 'Vue.js']],
        'status' => ['value' => 1, 'name' => 'Open', 'reject_details' => []],
        'type' => ['value' => '2', 'label' => 'Detailed Job'],
        'budget_from' => 15,
        'budget_to' => 40,
        'contract_time_type' => '1',
        'weekly_hours' => null,
        'experience_level' => '3',
        'duration' => null,
        'created_at' => '2026-09-12 12:58',
        'proposals' => 19,
        'proposal_count' => 19,
        'last_proposal_time' => '2026-09-14 11:47',
        'client' => ['id' => 342, 'name' => 'Taqat Recruiter', 'country' => ['id' => 183, 'name' => 'Palestine']],
        'milestones' => [],
        'allow_countries' => [],
        'number_of_open_positions' => 2,
        'is_open' => true,
        ...$overrides,
    ];
}

/**
 * Serves $pages as BrightGaza's paginated board. Taken by reference so a
 * test can change the board between two pulls.
 *
 * @param  list<list<array<string, mixed>>>  $pages
 */
function fakeBrightGazaBoard(array &$pages): void
{
    Http::fake([
        '*/api/v1/jobs*' => function (HttpRequest $request) use (&$pages) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = max(1, (int) ($query['page'] ?? 1));

            return Http::response([
                'status' => true,
                'code' => 200,
                'message' => 'ok',
                'data' => [
                    'jobs' => $pages[$page - 1] ?? [],
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => max(1, count($pages)),
                        'per_page' => 15,
                        'total' => count(array_merge(...$pages)),
                    ],
                ],
            ]);
        },
    ]);
}

test('pulling from BrightGaza creates every listed job on the receiving applications stage', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $pages = [[brightGazaListing(101), brightGazaListing(102)], [brightGazaListing(103, ['contract_time_type' => '2'])]];
    fakeBrightGazaBoard($pages);

    $this->postJson('/api/recruitment/brightgaza/jobs/import')
        ->assertOk()
        ->assertJsonPath('data.fetched', 3)
        ->assertJsonPath('data.created', 3)
        ->assertJsonPath('data.updated', 0);

    $job = JobRequirement::query()->where('external_source', 'brightgaza')->where('external_id', '101')->sole();

    expect($job->currentStage->code)->toBe('receiving_apps')
        ->and($job->status->value)->toBe('active')
        ->and((int) $job->owner_id)->toBe($admin->id)
        ->and($job->openings)->toBe(2)
        ->and($job->required_skills)->toBe(['Laravel', 'Vue.js'])
        ->and((float) $job->salary_min)->toBe(15.0)
        ->and((float) $job->salary_max)->toBe(40.0)
        ->and($job->publication_url)->toBe('https://brightgaza.com/ar/jobs/101')
        ->and($job->external_status)->toBe('Open')
        ->and($job->external_payload['proposal_count'])->toBe(19)
        ->and($job->external_payload['client']['name'])->toBe('Taqat Recruiter')
        ->and($job->description)->toContain('Work Type:')->not->toContain('<p>')
        ->and($job->recruitmentCase->client->company_name)->toBe('BrightGaza');
});

test('pulling again refreshes listed jobs without duplicating them or undoing TAQAT progress', function () {
    $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $pages = [[brightGazaListing(201)]];
    fakeBrightGazaBoard($pages);

    $this->postJson('/api/recruitment/brightgaza/jobs/import')->assertOk();

    $job = JobRequirement::query()->where('external_id', '201')->sole();
    $job->update(['current_stage_id' => $pipeline->stages->firstWhere('code', 'screening')->id]);

    $pages = [[brightGazaListing(201, ['title' => 'Senior Laravel Developer', 'proposal_count' => 25])]];

    $this->postJson('/api/recruitment/brightgaza/jobs/import')
        ->assertOk()
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.updated', 1);

    $job->refresh();

    expect(JobRequirement::query()->where('external_source', 'brightgaza')->count())->toBe(1)
        ->and($job->title)->toBe('Senior Laravel Developer')
        ->and($job->external_payload['proposal_count'])->toBe(25)
        ->and($job->currentStage->code)->toBe('screening')
        ->and(Client::query()->where('company_name', 'BrightGaza')->count())->toBe(1);
});

test('jobs that disappeared from the board are flagged, not deleted or closed', function () {
    $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $pages = [[brightGazaListing(301), brightGazaListing(302)]];
    fakeBrightGazaBoard($pages);

    $this->postJson('/api/recruitment/brightgaza/jobs/import')->assertOk();

    $pages = [[brightGazaListing(301)]];

    $this->postJson('/api/recruitment/brightgaza/jobs/import')
        ->assertOk()
        ->assertJsonPath('data.not_listed', 1);

    $gone = JobRequirement::query()->where('external_id', '302')->sole();

    expect($gone->external_status)->toBe('not_listed')
        ->and($gone->status->value)->toBe('active');
});

test('the board is read 100 jobs a page', function () {
    $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $pages = [[brightGazaListing(601)]];
    fakeBrightGazaBoard($pages);

    $this->postJson('/api/recruitment/brightgaza/jobs/import')->assertOk();

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'per_page=100'));
});

test('absurd numbers on the board are stored as unknown instead of failing the pull', function () {
    // Seen in production on 2026-09-14: job 107 listed 1015276301 open
    // positions, which MySQL rejects for the unsigned smallint column.
    $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $pages = [[brightGazaListing(701, [
        'number_of_open_positions' => 1015276301,
        'budget_from' => -5,
        'budget_to' => 5_000_000_000_000,
        'created_at' => '0000-00-00 00:00',
        'status' => ['value' => 9, 'name' => str_repeat('Open ', 20)],
    ])]];
    fakeBrightGazaBoard($pages);

    $this->postJson('/api/recruitment/brightgaza/jobs/import')
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.failed', 0);

    $job = JobRequirement::query()->where('external_id', '701')->sole();

    expect($job->openings)->toBe(1)
        ->and($job->salary_min)->toBeNull()
        ->and($job->salary_max)->toBeNull()
        ->and($job->published_at)->toBeNull()
        ->and(mb_strlen($job->external_status))->toBe(50)
        ->and($job->external_payload['number_of_open_positions'])->toBe(1015276301);
});

test('a listing that cannot be saved is reported without stopping the others', function () {
    $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $pages = [[brightGazaListing(801), brightGazaListing(802)]];
    fakeBrightGazaBoard($pages);

    $this->partialMock(JobRequirementRepository::class, function ($mock) {
        $mock->shouldReceive('create')->andReturnUsing(function (array $attributes) {
            if ($attributes['external_id'] === '801') {
                throw new RuntimeException('simulated database rejection');
            }

            return JobRequirement::query()->create($attributes);
        });
    });

    $this->postJson('/api/recruitment/brightgaza/jobs/import')
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.failed', 1)
        ->assertJsonPath('data.failed_ids', ['801']);

    expect(JobRequirement::query()->where('external_id', '802')->exists())->toBeTrue()
        ->and(JobRequirement::query()->where('external_id', '801')->exists())->toBeFalse();
});

test('pulling from BrightGaza needs manage-jobs', function () {
    $this->seedStandardPipeline();
    $pages = [[brightGazaListing(401)]];
    fakeBrightGazaBoard($pages);
    $this->actingAsUserWithPermissions(['view-jobs']);

    $this->postJson('/api/recruitment/brightgaza/jobs/import')->assertForbidden();

    expect(JobRequirement::query()->count())->toBe(0);
});

test('an unreachable board answers 502 and writes nothing', function () {
    $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    Http::fake(['*/api/v1/jobs*' => Http::response(['message' => 'down'], 503)]);

    $this->postJson('/api/recruitment/brightgaza/jobs/import')->assertStatus(502);

    expect(JobRequirement::query()->count())->toBe(0)
        ->and(Client::query()->where('company_name', 'BrightGaza')->exists())->toBeFalse();
});
