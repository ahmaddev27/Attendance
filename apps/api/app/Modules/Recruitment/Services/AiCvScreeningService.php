<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\JobRequirement;
use App\Modules\AI\Services\ClaudeClient;
use App\Modules\Recruitment\Exceptions\AiScreeningException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Asks Claude to score a candidate against a job from professional
 * profile fields only. Contact details, national id and the resume file
 * are never sent: this first slice judges the structured profile, and a
 * later slice can add resume text extraction.
 */
class AiCvScreeningService
{
    private const CACHE_TTL_SECONDS = 86400;
    private const MAX_TOKENS = 1024;
    private const RECOMMENDATIONS = ['advance', 'reject', 'maybe'];

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a recruitment screening assistant. Compare the candidate profile to the job and respond with STRICT JSON only, no prose and no markdown fences, using exactly these keys:
{"overall_score": number 0-100, "summary": string, "strengths": string[], "concerns": string[], "skill_match": {"<required skill>": boolean}, "recommendation": "advance" | "reject" | "maybe"}
Base the judgement only on the data provided. Do not infer protected characteristics. Write summary, strengths and concerns in the same language as the job title.
PROMPT;

    public function __construct(
        private readonly ClaudeClient $claude,
    ) {}

    /**
     * @return array{overall_score: float, summary: string, strengths: list<string>, concerns: list<string>, skill_match: array<string, bool>, recommendation: string}
     *
     * @throws AiScreeningException
     */
    public function screen(CandidateApplication $application): array
    {
        $application->loadMissing(['candidate', 'jobRequirement']);
        $job = $application->jobRequirement;
        $candidate = $application->candidate;

        // Keyed on both updated_at stamps so editing either side
        // invalidates the verdict; a plain re-click is served from cache.
        $key = sprintf(
            'ai-cv-screen:%d:%d:%d',
            $application->id,
            $job->updated_at?->getTimestamp() ?? 0,
            $candidate->updated_at?->getTimestamp() ?? 0,
        );

        return Cache::remember($key, self::CACHE_TTL_SECONDS, fn (): array => $this->requestScreening($job, $candidate));
    }

    /**
     * @return array{overall_score: float, summary: string, strengths: list<string>, concerns: list<string>, skill_match: array<string, bool>, recommendation: string}
     */
    private function requestScreening(JobRequirement $job, Candidate $candidate): array
    {
        $userPrompt = json_encode(
            ['job' => $this->jobPayload($job), 'candidate' => $this->candidatePayload($candidate)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        try {
            $raw = $this->claude->message(self::SYSTEM_PROMPT, (string) $userPrompt, self::MAX_TOKENS);
        } catch (Throwable $e) {
            throw new AiScreeningException('Claude call failed: '.$e->getMessage(), 0, $e);
        }

        return $this->parse($raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(JobRequirement $job): array
    {
        return [
            'title' => $job->title,
            'description' => $job->description,
            'responsibilities' => $job->responsibilities,
            'required_skills' => $job->required_skills ?? [],
            'nice_to_have_skills' => $job->nice_to_have_skills ?? [],
            'required_experience_years' => $job->required_experience_years,
            'required_languages' => $job->required_languages ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function candidatePayload(Candidate $candidate): array
    {
        return [
            'headline' => $candidate->headline,
            'years_of_experience' => $candidate->years_of_experience,
            'current_title' => $candidate->current_title,
            'skills' => $candidate->skills ?? [],
            'languages' => $candidate->languages ?? [],
            'notes' => $candidate->notes,
        ];
    }

    /**
     * @return array{overall_score: float, summary: string, strengths: list<string>, concerns: list<string>, skill_match: array<string, bool>, recommendation: string}
     */
    private function parse(string $raw): array
    {
        // Models sometimes wrap JSON in fences despite instructions.
        $json = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)));
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['overall_score'], $data['summary'], $data['recommendation'])
            || ! in_array($data['recommendation'], self::RECOMMENDATIONS, true)) {
            throw new AiScreeningException('Claude returned an unusable screening payload');
        }

        return [
            'overall_score' => max(0.0, min(100.0, (float) $data['overall_score'])),
            'summary' => (string) $data['summary'],
            'strengths' => $this->stringList($data['strengths'] ?? []),
            'concerns' => $this->stringList($data['concerns'] ?? []),
            'skill_match' => $this->boolMap($data['skill_match'] ?? []),
            'recommendation' => $data['recommendation'],
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('strval', array_filter($value, 'is_scalar'))) : [];
    }

    /**
     * @return array<string, bool>
     */
    private function boolMap(mixed $value): array
    {
        return is_array($value) ? array_map('boolval', array_filter($value, 'is_scalar')) : [];
    }
}
