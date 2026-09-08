<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Models\Employee;
use App\Models\User;
use App\Modules\AI\Exceptions\MotivationUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates the daily motivational message for one user:
 * cache-first, LLM on miss, canned fallback on failure. Any single
 * dashboard load must return in bounded time and never 500 because
 * of an LLM outage or a missing API key — that guarantee is why the
 * fallback path exists and why we never cache a fallback (so recovery
 * is picked up on the next page load, not blocked until tomorrow).
 */
class MotivationService
{
    private const CACHE_KEY_FORMAT = 'motivation:user:%d:%s';
    private const CACHE_TTL_HOURS = 24;
    private const MAX_TOKENS = 512;

    private const SYSTEM_PROMPT = <<<'PROMPT'
    أنت مساعد ودود لموظفي شركة "طاقات". مهمتك أن تكتب رسالة تحفيزية قصيرة (من جملتين إلى ثلاث جمل) موجّهة لموظف بعينه.

    قواعد صارمة:
    - اكتب بالعربية الفصحى المبسّطة، بأسلوب دافئ وشخصي، دون مبالغة ودون تعابير رنّانة أو خطابية.
    - وظِّف الأرقام الواردة في بيانات الموظف (سلسلة الحضور، المهام المنجزة، المهام المفتوحة، الإجازة القادمة) بشكل طبيعي داخل الجملة كي تبدو الرسالة شخصية فعلًا، لا نموذجًا معلّبًا.
    - نادِ الموظف باسمه الأول عند الحاجة، ولا تستخدم عبارات مثل "عزيزي الموظف" أو "بطلنا" أو أي شعارات مؤسسية.
    - لا تذكر أنك ذكاء اصطناعي، ولا تشرح ما تفعل، ولا تضف عناوين أو مقدّمات — أعِد النصّ مباشرةً.
    PROMPT;

    public function __construct(
        private readonly MotivationContextBuilder $contextBuilder,
        private readonly ClaudeClient $claude,
    ) {}

    /**
     * @return array{message: string, generated_at: string, cached: bool}
     */
    public function for(User $user): array
    {
        $now = CarbonImmutable::now();
        $cacheKey = $this->cacheKey($user->id, $now);

        /** @var array{message: string, generated_at: string}|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return [
                'message' => $cached['message'],
                'generated_at' => $cached['generated_at'],
                'cached' => true,
            ];
        }

        $employee = $user->employee;
        if (! $employee instanceof Employee) {
            // A login without an employee profile still gets a
            // motivational line, just from the canned pool. Don't
            // cache — a real employee link may appear later today.
            return $this->freshResponse($this->fallback($now));
        }

        try {
            $context = $this->contextBuilder->build($employee, $now->startOfDay());
            $message = $this->claude->message(
                self::SYSTEM_PROMPT,
                $this->buildUserPrompt($context),
                self::MAX_TOKENS,
            );
        } catch (MotivationUnavailableException) {
            // Deliberately not cached so a transient upstream outage
            // heals itself on the next page load rather than sticking
            // a canned message on the user for the rest of the day.
            return $this->freshResponse($this->fallback($now));
        }

        $response = $this->freshResponse($message);
        Cache::put(
            $cacheKey,
            ['message' => $response['message'], 'generated_at' => $response['generated_at']],
            $now->addHours(self::CACHE_TTL_HOURS),
        );

        return $response;
    }

    /**
     * @return array{message: string, generated_at: string, cached: false}
     */
    private function freshResponse(string $message): array
    {
        return [
            'message' => $message,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'cached' => false,
        ];
    }

    private function cacheKey(int $userId, CarbonImmutable $today): string
    {
        return sprintf(self::CACHE_KEY_FORMAT, $userId, $today->toDateString());
    }

    /**
     * Deterministic weekday rotation over the canned pool — running
     * the endpoint twice on the same day returns the same fallback,
     * so a cache-miss retry doesn't shuffle the message on the user.
     */
    private function fallback(CarbonImmutable $today): string
    {
        /** @var list<string> $pool */
        $pool = require resource_path('motivation-fallbacks.php');

        if ($pool === []) {
            return 'يوم موفّق لك اليوم. اجعل خطوتك الأولى بسيطة، والبقيّة ستتبعها.';
        }

        return $pool[$today->dayOfWeek % count($pool)];
    }

    /**
     * @param  array{
     *   employee_name: string,
     *   attendance_streak: int,
     *   tasks_completed_this_week: int,
     *   open_tasks: int,
     *   upcoming_leave: array{start_date: string, end_date: string, leave_type: ?string}|null,
     *   total_attendance_days_this_month: int,
     * }  $context
     */
    private function buildUserPrompt(array $context): string
    {
        $upcoming = $context['upcoming_leave'];
        $upcomingLine = $upcoming === null
            ? 'لا توجد إجازة قادمة معتمدة'
            : sprintf(
                '%s من %s إلى %s',
                $upcoming['leave_type'] ?? 'إجازة',
                $upcoming['start_date'],
                $upcoming['end_date'],
            );

        return sprintf(
            "بيانات الموظف اليوم:\n" .
            "- الاسم: %s\n" .
            "- سلسلة أيام الحضور المتتالية هذا الشهر: %d\n" .
            "- إجمالي أيام الحضور هذا الشهر: %d\n" .
            "- المهام المنجزة هذا الأسبوع: %d\n" .
            "- المهام المفتوحة حاليًا: %d\n" .
            "- الإجازة القادمة: %s\n\n" .
            'اكتب الرسالة التحفيزية الآن (جملتان إلى ثلاث جمل، دون مقدّمة).',
            $context['employee_name'],
            $context['attendance_streak'],
            $context['total_attendance_days_this_month'],
            $context['tasks_completed_this_week'],
            $context['open_tasks'],
            $upcomingLine,
        );
    }
}
