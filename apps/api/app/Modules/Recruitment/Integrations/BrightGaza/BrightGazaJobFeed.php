<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Integrations\BrightGaza;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads BrightGaza's public job board (GET /api/v1/jobs). No credentials are
 * involved: it is the same list anyone sees on brightgaza.com/jobs.
 */
class BrightGazaJobFeed
{
    /**
     * Their default page is 15 jobs and each page takes seconds to answer;
     * one 100-job page read the whole board (78 jobs) in about 3 s instead
     * of 6 pages in about 32 s, which ran into the web server's timeout.
     */
    private const PER_PAGE = 100;

    /** Guards against a pagination bug on their side looping forever. */
    private const MAX_PAGES = 50;

    /**
     * @return list<array<string, mixed>>
     *
     * @throws BrightGazaUnavailableException
     */
    public function openJobs(): array
    {
        $jobs = [];
        $page = 1;

        do {
            $data = $this->page($page);

            foreach ($data['jobs'] ?? [] as $job) {
                if (is_array($job)) {
                    $jobs[] = $job;
                }
            }

            $lastPage = (int) ($data['pagination']['last_page'] ?? $page);
            $page++;
        } while ($page <= $lastPage && $page <= self::MAX_PAGES);

        return $jobs;
    }

    /**
     * @return array<string, mixed>
     */
    private function page(int $page): array
    {
        try {
            $response = Http::baseUrl((string) config('services.brightgaza.api_url'))
                ->acceptJson()
                ->timeout(15)
                ->retry(
                    2,
                    300,
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                    throw: false,
                )
                ->get('/api/v1/jobs', ['page' => $page, 'per_page' => self::PER_PAGE]);
        } catch (ConnectionException $e) {
            throw BrightGazaUnavailableException::because($e->getMessage());
        }

        $data = $response->json('data');

        if (! $response->successful() || ! is_array($data)) {
            throw BrightGazaUnavailableException::because("HTTP {$response->status()} on page {$page}");
        }

        return $data;
    }
}
