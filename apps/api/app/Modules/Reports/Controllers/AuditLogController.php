<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * GET /api/admin/audit-log
     *   ?subject_type=&causer_id=&log_name=&from=&to=&per_page=
     *
     * Reads through ActivityLog, the configured activity model, so anything
     * that calls activity()->log() or uses LogsActivity shows up here for free.
     * No paginated response for very old batches because the table is
     * truncated by Spatie's DB pruner (activitylog.delete_records_older_than_days).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $activities = ActivityLog::query()
            ->with('causer:id,name,employee_number')
            ->latest('id')
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('log_name'), fn ($q) => $q->where('log_name', $request->string('log_name')))
            ->when($request->filled('causer_id'), fn ($q) => $q->where('causer_id', (int) $request->input('causer_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')))
            ->paginate((int) $request->integer('per_page', self::PER_PAGE));

        // Emit a lightweight, uniform shape — Spatie's own Activity model
        // doesn't ship a JSON resource, and returning the full model would
        // expose implementation-detail columns (properties JSON as a bag).
        return AnonymousResourceCollection::make($activities, new class(null) extends \Illuminate\Http\Resources\Json\JsonResource {
            public function toArray($request): array
            {
                /** @var ActivityLog $activity */
                $activity = $this->resource;

                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'log_name' => $activity->log_name,
                    'event' => $activity->event,
                    'subject_type' => class_basename($activity->subject_type ?? ''),
                    'subject_id' => $activity->subject_id,
                    'causer' => $activity->causer ? [
                        'id' => $activity->causer->id,
                        'name' => $activity->causer->name,
                        'employee_number' => $activity->causer->employee_number,
                    ] : null,
                    'properties' => $activity->properties?->toArray() ?? [],
                    'created_at' => $activity->created_at?->toIso8601String(),
                ];
            }
        });
    }
}
