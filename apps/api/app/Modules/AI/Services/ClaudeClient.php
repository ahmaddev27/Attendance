<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\AI\Exceptions\MotivationUnavailableException;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin transport-level wrapper over the Anthropic Messages API.
 *
 * Intentionally does not know anything about motivation, cache keys
 * or user context — that lives in MotivationService. This class only
 * cares about: authenticate the call, apply a bounded timeout with a
 * small exponential-backoff retry, and convert any non-2xx / empty
 * body / network failure into a single MotivationUnavailableException
 * upstream code can catch to trigger a fallback.
 */
class ClaudeClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const DEFAULT_MODEL = 'claude-sonnet-5';
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const TIMEOUT_SECONDS = 15;
    private const RETRY_ATTEMPTS = 3; // one initial + two retries
    private const RETRY_BASE_MS = 500;

    private readonly string $apiKey;
    private readonly string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        // Both key and model default to the admin-editable settings first,
        // then fall back to env vars. Tests can pass explicit values.
        $settings = null;
        try {
            $settings = App::make(SettingsService::class);
        } catch (Throwable) {
            // Container not booted yet — env fallback below is fine.
        }

        $this->apiKey = $apiKey
            ?? ($settings?->get('ai.anthropic_api_key', 'services.anthropic.api_key'))
            ?? (string) env('ANTHROPIC_API_KEY', '');

        $this->model = $model
            ?? ($settings?->get('ai.anthropic_model', 'services.anthropic.model', self::DEFAULT_MODEL))
            ?? self::DEFAULT_MODEL;
    }

    /**
     * @throws MotivationUnavailableException on non-2xx after retries,
     *   empty content, or connection-level failure.
     */
    public function message(string $systemPrompt, string $userPrompt, int $maxTokens = 512): string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->withHeaders([
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'content-type' => 'application/json',
                ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(
                    self::RETRY_ATTEMPTS,
                    fn (int $attempt): int => self::RETRY_BASE_MS * (2 ** ($attempt - 1)),
                    when: static function (Throwable $exception): bool {
                        // Retry on transport hiccups and 5xx; a 4xx
                        // (bad key, malformed body) won't heal, so
                        // don't waste attempts on it.
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }
                        if ($exception instanceof RequestException) {
                            return $exception->response->serverError();
                        }

                        return false;
                    },
                    // throw: true (default) — after all retries are
                    // exhausted, let the final exception propagate so
                    // the surrounding catch converts it uniformly.
                )
                ->throw()
                ->post(self::ENDPOINT, [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    // No reasoning needed for a 2-3 sentence generation;
                    // disabling keeps latency and cost predictable.
                    'thinking' => ['type' => 'disabled'],
                ]);
        } catch (Throwable $exception) {
            throw new MotivationUnavailableException(
                'Claude API call failed: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        $text = $this->extractText($response->json());
        if ($text === null) {
            throw new MotivationUnavailableException('Claude returned no text content');
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function extractText(?array $body): ?string
    {
        if (! is_array($body) || ! isset($body['content']) || ! is_array($body['content'])) {
            return null;
        }

        foreach ($body['content'] as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'text') {
                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }
}
