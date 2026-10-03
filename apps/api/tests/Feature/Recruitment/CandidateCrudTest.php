<?php

declare(strict_types=1);

use App\Models\Candidate;
use App\Shared\Enums\CandidateStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('listing candidates requires view-candidates', function () {
    $this->actingAsUserWithPermissions([]);

    $this->getJson('/api/candidates')->assertForbidden();
});

test('an admin lists active candidates by default', function () {
    $this->actingAsRecruitmentAdmin();

    Candidate::factory()->count(3)->create();
    Candidate::factory()->blacklisted()->create();

    $response = $this->getJson('/api/candidates')->assertOk();

    // Default scope hides blacklisted + inactive — the owner's "clean
    // picker" rule from CandidateRepository::applyFilters.
    expect($response->json('data'))->toHaveCount(3);
});

test('a candidate is created with a generated candidate_number and the acting user as creator', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $response = $this->postJson('/api/candidates', [
        'full_name' => 'Ahmad Tester',
        'email' => 'ahmad.tester@example.com',
        'phone' => '+970599000111',
        'headline' => 'Senior Backend',
        'years_of_experience' => 7,
    ])->assertCreated();

    $year = (int) date('Y');
    $candidateNumber = $response->json('data.candidate_number');

    expect($candidateNumber)->toStartWith("CAN-{$year}-");

    $candidate = Candidate::firstWhere('candidate_number', $candidateNumber);
    expect($candidate->created_by_user_id)->toBe($admin->id);
    expect($candidate->email)->toBe('ahmad.tester@example.com');
});

test('creating a candidate without either email or phone fails validation', function () {
    $this->actingAsRecruitmentAdmin();

    $this->postJson('/api/candidates', [
        'full_name' => 'No Contact',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('creating a candidate requires manage-candidates — view alone is not enough', function () {
    $this->actingAsUserWithPermissions(['view-candidates']);

    $this->postJson('/api/candidates', [
        'full_name' => 'Blocked Create',
        'email' => 'blocked@example.com',
    ])->assertForbidden();
});

test('updating a candidate normalises email to lowercase', function () {
    $this->actingAsRecruitmentAdmin();

    $candidate = Candidate::factory()->create(['email' => 'old@example.com']);

    $this->patchJson("/api/candidates/{$candidate->id}", [
        'email' => 'NEW@EXAMPLE.COM',
    ])->assertOk();

    expect($candidate->fresh()->email)->toBe('new@example.com');
});

test('deleting a candidate is a soft delete', function () {
    $this->actingAsRecruitmentAdmin();

    $candidate = Candidate::factory()->create();

    $this->deleteJson("/api/candidates/{$candidate->id}")->assertNoContent();

    // Scoped find hides it; withTrashed() still sees it with trashed()=true.
    expect(Candidate::find($candidate->id))->toBeNull();
    expect(Candidate::withTrashed()->find($candidate->id)->trashed())->toBeTrue();
});

test('the candidate resource marks blacklisted status and omits the private resume path', function () {
    $this->actingAsRecruitmentAdmin();

    $candidate = Candidate::factory()->create(['status' => CandidateStatus::Blacklisted->value]);

    $response = $this->getJson("/api/candidates/{$candidate->id}")->assertOk();

    expect($response->json('data.status'))->toBe('blacklisted');
    // The server never leaks the resume_path storage key — a signed URL
    // is the only way to the file (resume is null on the factory).
    expect($response->json('data'))->not->toHaveKey('resume_path');
    expect($response->json('data.has_resume'))->toBeFalse();
});
