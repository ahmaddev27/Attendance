<?php

declare(strict_types=1);

namespace App\Modules\Auth\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sign-ins and sign-outs share the activity_log stream with data changes
 * so an investigation can line up "who was signed in, and from where"
 * against "what changed" without a second data source.
 */
class RecordAuthenticationActivity
{
    public function login(Login $event): void
    {
        $this->record($event->user, 'login');
    }

    public function logout(Logout $event): void
    {
        $this->record($event->user, 'logout');
    }

    private function record(?Authenticatable $user, string $description): void
    {
        if (! $user instanceof Model) {
            return;
        }

        activity('auth')
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip()])
            ->log($description);
    }
}
