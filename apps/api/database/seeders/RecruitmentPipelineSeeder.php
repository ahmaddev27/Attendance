<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RecruitmentPipeline;
use App\Models\RecruitmentPipelineStage;
use App\Shared\Enums\StageOwnerRule;
use Illuminate\Database\Seeder;

/**
 * Seeds the default "Standard Job Pipeline" — the 10-stage flow every
 * new JobRequirement is attached to unless an alternative pipeline is
 * explicitly chosen. Idempotent: reruns update matching rows in place
 * (firstOrCreate + fill).
 *
 * The middle Phase 2/3 stages (screening / interviewing / contracting)
 * are seeded here so the pipeline is complete end-to-end from day one;
 * the UI/logic for actually driving jobs through them ships in the
 * later phases. Advancing INTO those stages already works in Phase 1
 * — advancing beyond them meaningfully doesn't.
 */
class RecruitmentPipelineSeeder extends Seeder
{
    public function run(): void
    {
        $pipeline = RecruitmentPipeline::firstOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standard Job Pipeline',
                'description' => 'Default hiring workflow from job creation through hire.',
                // Only claim the default slot when it is free — an admin who
                // already picked a default pipeline keeps it.
                'is_default' => ! RecruitmentPipeline::query()->where('is_default', true)->exists(),
                'is_active' => true,
            ]
        );

        $stages = [
            // [code, name, owner_rule_type, owner_rule_value, sla_hours, auto_task, is_terminal, requires_fields, task_title_template, task_priority]
            ['new',              'New Job',            StageOwnerRule::JobOwner->value,           null,                    null,  false, false, [],                     null,                                   null],
            ['publish',          'Publish Job',        StageOwnerRule::Role->value,               'publish-jobs',          24,    true,  false, ['publication_url'],    'Publish job: {job.title}',             'high'],
            ['receiving_apps',   'Receiving Applic.',  StageOwnerRule::Role->value,               'screen-candidates',     168,   false, false, [],                     null,                                   null],
            ['screening',        'Screening',          StageOwnerRule::Role->value,               'screen-candidates',     72,    true,  false, [],                     'Screen applicants for: {job.title}',   'normal'],
            ['shortlist',        'Shortlist',          StageOwnerRule::PreviousStageOwner->value, null,                    24,    true,  false, [],                     'Finalize shortlist for: {job.title}',  'high'],
            ['interviewing',     'Interviewing',       StageOwnerRule::Role->value,               'schedule-interviews',   168,   true,  false, [],                     'Schedule interviews for: {job.title}', 'normal'],
            ['client_decision',  'Client Decision',    StageOwnerRule::JobOwner->value,           null,                    72,    false, false, [],                     null,                                   null],
            ['contracting',      'Contracting',        StageOwnerRule::Role->value,               'prepare-contracts',     48,    true,  false, [],                     'Prepare contract for: {job.title}',    'high'],
            ['hired',            'Hired',              StageOwnerRule::None->value,               null,                    null,  false, true,  [],                     null,                                   null],
            ['cancelled',        'Cancelled',          StageOwnerRule::None->value,               null,                    null,  false, true,  [],                     null,                                   null],
        ];

        foreach ($stages as $order => [$code, $name, $ruleType, $ruleValue, $sla, $autoTask, $terminal, $requires, $taskTitle, $taskPriority]) {
            RecruitmentPipelineStage::updateOrCreate(
                [
                    'pipeline_id' => $pipeline->id,
                    'code' => $code,
                ],
                [
                    'display_order' => $order + 1,
                    'name' => $name,
                    'owner_rule_type' => $ruleType,
                    'owner_rule_value' => $ruleValue,
                    'sla_hours' => $sla,
                    'auto_generate_task' => $autoTask,
                    'task_title_template' => $taskTitle,
                    'task_priority' => $taskPriority,
                    'requires_fields' => $requires,
                    'is_terminal' => $terminal,
                ]
            );
        }
    }
}
