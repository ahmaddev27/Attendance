<?php

declare(strict_types=1);

namespace App\Modules\Tasks;

use App\Modules\Tasks\Events\CommentCreated;
use App\Modules\Tasks\Listeners\NotifyMentionedUsers;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Event wiring for the Tasks module, kept out of AppServiceProvider so the
 * module stays self-contained, the same way RecruitmentServiceProvider is.
 */
class TasksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CommentCreated::class, NotifyMentionedUsers::class);
    }
}
