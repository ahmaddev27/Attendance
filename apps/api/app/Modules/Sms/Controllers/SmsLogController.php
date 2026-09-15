<?php

declare(strict_types=1);

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sms\Requests\ListSmsLogsRequest;
use App\Modules\Sms\Resources\SmsLogResource;
use App\Modules\Sms\Services\SmsLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmsLogController extends Controller
{
    public function __construct(
        private readonly SmsLogService $logs,
    ) {}

    /**
     * `GET /api/admin/sms-logs?limit=` — the latest SMS attempts with the
     * carrier's answer, shown under the SMS settings.
     */
    public function index(ListSmsLogsRequest $request): AnonymousResourceCollection
    {
        return SmsLogResource::collection(
            $this->logs->recent($request->integer('limit', SmsLogService::DEFAULT_LIMIT)),
        );
    }
}
