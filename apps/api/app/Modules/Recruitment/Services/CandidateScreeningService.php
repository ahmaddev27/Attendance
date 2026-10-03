<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\CandidateApplication;
use App\Models\CandidateScreening;
use App\Models\RecruitmentPipelineStage;
use App\Models\User;
use App\Shared\Enums\CandidateApplicationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the scorecard lifecycle for a CandidateApplication. The scorecard
 * schema lives on `recruitment_pipeline_stages.screening_schema` (D3 in
 * the plan) — admins edit it from the stage editor; this service
 * validates each submitted answer against that schema and derives the
 * overall score + pass flag per the pass_threshold.
 *
 * Re-scoring (store on an existing row) overwrites the single row
 * instead of versioning — Phase 2 is deliberately history-free (D5
 * covers versioning as a Phase 3 refinement). The audit log on the
 * model captures each change for traceability.
 */
class CandidateScreeningService
{
    public function find(CandidateApplication $application): ?CandidateScreening
    {
        return CandidateScreening::query()->where('application_id', $application->id)->first();
    }

    /**
     * @param  array<string, mixed>  $scorecard
     */
    public function store(
        CandidateApplication $application,
        User $actor,
        array $scorecard,
        ?string $notes = null,
    ): CandidateScreening {
        return DB::transaction(function () use ($application, $actor, $scorecard, $notes): CandidateScreening {
            if ($application->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إجراء الفرز على طلب مغلق.',
                ]);
            }

            $stage = $application->currentStage()->first();
            $schema = $stage?->screening_schema;

            if (! is_array($schema) || empty($schema['fields'] ?? null)) {
                throw ValidationException::withMessages([
                    'scorecard' => 'المرحلة الحالية لا تحتوي على نموذج فرز معرّف.',
                ]);
            }

            $normalised = $this->validateAgainstSchema($schema, $scorecard);
            $overall = $this->computeOverallScore($schema, $normalised);
            $threshold = $this->passThreshold($schema);
            $passed = $overall !== null && $overall >= $threshold;

            /** @var CandidateScreening $screening */
            $screening = CandidateScreening::query()->updateOrCreate(
                ['application_id' => $application->id],
                [
                    'scored_by_user_id' => $actor->id,
                    'scorecard' => $normalised,
                    // Score is scaled to 0–100 so the admin UI's visual
                    // bar can read uniformly across differently-scaled
                    // schemas (1–5 scales, free numbers, etc.).
                    'overall_score' => $overall !== null ? round(($overall / 5) * 100, 2) : null,
                    'passed' => $passed,
                    'recommendation' => $passed ? 'advance' : 'reject',
                    'notes' => $notes,
                    'scored_at' => now(),
                ],
            );

            // Mirror the pass/fail on the application status so an admin
            // filter by status gives the same answer as a filter by
            // screening.passed. Status is only touched for the two paths
            // the screening resolves to; manual admin changes still win.
            $this->applications()->where('id', $application->id)->update([
                'status' => $passed
                    ? CandidateApplicationStatus::ScreenedIn->value
                    : CandidateApplicationStatus::ScreenedOut->value,
            ]);

            return $screening;
        });
    }

    public function schemaForStage(RecruitmentPipelineStage $stage): ?array
    {
        return is_array($stage->screening_schema) ? $stage->screening_schema : null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $scorecard
     * @return array<string, mixed>
     */
    private function validateAgainstSchema(array $schema, array $scorecard): array
    {
        $errors = [];
        $out = [];

        foreach ($schema['fields'] as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'number');
            $raw = $scorecard[$key] ?? null;

            if ($raw === null || $raw === '') {
                $errors["scorecard.{$key}"] = 'هذا الحقل مطلوب.';

                continue;
            }

            $out[$key] = match ($type) {
                'rating_1_5' => $this->ensureRating($raw, 1, 5, $key, $errors),
                'number' => $this->ensureNumber($raw, $key, $errors),
                'select' => $this->ensureOption($raw, (array) ($field['options'] ?? []), $key, $errors),
                default => (string) $raw,
            };
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $scorecard
     */
    private function computeOverallScore(array $schema, array $scorecard): ?float
    {
        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($schema['fields'] as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'number');
            $weight = (float) ($field['weight'] ?? 0);

            if ($weight <= 0 || ! isset($scorecard[$key])) {
                continue;
            }

            // Only the two numeric types contribute to the weighted
            // average — `select`/`text` are context, not score.
            if (! in_array($type, ['rating_1_5', 'number'], true)) {
                continue;
            }

            $weightedSum += $weight * (float) $scorecard[$key];
            $totalWeight += $weight;
        }

        if ($totalWeight === 0.0) {
            return null;
        }

        return $weightedSum / $totalWeight;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function passThreshold(array $schema): float
    {
        return (float) ($schema['pass_threshold'] ?? 3.0);
    }

    /** @param  array<string, string>  $errors */
    private function ensureRating(mixed $raw, int $min, int $max, string $key, array &$errors): int
    {
        if (! is_numeric($raw)) {
            $errors["scorecard.{$key}"] = 'يجب أن يكون رقماً.';

            return 0;
        }

        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            $errors["scorecard.{$key}"] = "القيمة يجب أن تكون بين {$min} و{$max}.";
        }

        return $value;
    }

    /** @param  array<string, string>  $errors */
    private function ensureNumber(mixed $raw, string $key, array &$errors): float
    {
        if (! is_numeric($raw)) {
            $errors["scorecard.{$key}"] = 'يجب أن يكون رقماً.';

            return 0.0;
        }

        return (float) $raw;
    }

    /**
     * @param  list<string>  $options
     * @param  array<string, string>  $errors
     */
    private function ensureOption(mixed $raw, array $options, string $key, array &$errors): string
    {
        $value = (string) $raw;
        if (! in_array($value, $options, true)) {
            $errors["scorecard.{$key}"] = 'القيمة ليست من الخيارات المسموحة.';
        }

        return $value;
    }

    private function applications()
    {
        return \App\Models\CandidateApplication::query();
    }
}
