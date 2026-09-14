<?php

declare(strict_types=1);

namespace App\Modules\Auth\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

/**
 * Role pivots are written without model events, so role changes are only
 * visible through spatie/permission's own attach/detach events
 * (permission.events_enabled). Only ids are recorded: resolving names
 * here would add a query to every assignment.
 */
class RecordRoleAssignmentActivity
{
    public function attached(RoleAttached $event): void
    {
        $this->record($event->model, $event->rolesOrIds, 'roles_assigned');
    }

    public function detached(RoleDetached $event): void
    {
        $this->record($event->model, $event->rolesOrIds, 'roles_removed');
    }

    private function record(Model $model, mixed $rolesOrIds, string $description): void
    {
        $roleIds = $this->roleIds($rolesOrIds);

        if ($roleIds === []) {
            return;
        }

        activity('users')
            ->performedOn($model)
            ->withProperties(['role_ids' => $roleIds])
            ->log($description);
    }

    /**
     * The event contract allows ids, a single Role or a collection of them.
     *
     * @return list<int|string>
     */
    private function roleIds(mixed $rolesOrIds): array
    {
        return Collection::wrap($rolesOrIds)
            ->map(fn (mixed $role): mixed => $role instanceof Model ? $role->getKey() : $role)
            ->values()
            ->all();
    }
}
