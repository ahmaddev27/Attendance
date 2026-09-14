<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Auth\Listeners\RecordAuthenticationActivity;
use App\Modules\Auth\Listeners\RecordRoleAssignmentActivity;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

/**
 * Registered explicitly rather than through app/Listeners discovery so the
 * Auth module's audit hooks sit next to the code that raises the events,
 * mirroring RecruitmentServiceProvider.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, [RecordAuthenticationActivity::class, 'login']);
        Event::listen(Logout::class, [RecordAuthenticationActivity::class, 'logout']);

        Event::listen(RoleAttached::class, [RecordRoleAssignmentActivity::class, 'attached']);
        Event::listen(RoleDetached::class, [RecordRoleAssignmentActivity::class, 'detached']);
    }
}
