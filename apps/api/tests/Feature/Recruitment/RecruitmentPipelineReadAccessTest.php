<?php

use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * The job page, the advance-stage dialog and the job form all read the
 * pipeline and its stages. Anyone allowed to see jobs has to be able to read
 * them; changing a pipeline stays with manage-recruitment-pipelines.
 */
test('staff who can view jobs can read pipelines and their stages', function () {
    $pipeline = $this->seedStandardPipeline();
    $this->actingAsUserWithPermissions(['view-jobs']);

    $this->getJson('/api/recruitment-pipelines')->assertOk();

    $this->getJson("/api/recruitment-pipelines/{$pipeline->id}")
        ->assertOk()
        ->assertJsonCount(10, 'data.stages');
});

test('reading pipelines does not let job viewers change them', function () {
    $pipeline = $this->seedStandardPipeline();
    $this->actingAsUserWithPermissions(['view-jobs']);

    $this->patchJson("/api/recruitment-pipelines/{$pipeline->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->postJson('/api/recruitment-pipelines', ['code' => 'other', 'name' => 'Other'])->assertForbidden();
});

test('users without a recruitment permission still cannot read pipelines', function () {
    $this->seedStandardPipeline();
    $this->actingAsUserWithPermissions([]);

    $this->getJson('/api/recruitment-pipelines')->assertForbidden();
});
