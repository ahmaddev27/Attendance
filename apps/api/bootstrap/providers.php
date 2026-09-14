<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RateLimiterServiceProvider::class,
    App\Modules\Auth\AuthServiceProvider::class,
    App\Modules\Recruitment\RecruitmentServiceProvider::class,
    App\Modules\Tasks\TasksServiceProvider::class,
];
