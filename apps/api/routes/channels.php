<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorization
|--------------------------------------------------------------------------
|
| Laravel's Notifiable trait broadcasts on the channel named
| `App.Models.User.{id}` by convention. We authorize a subscription only
| when the connected user IS that user — everyone can be told about their
| own notifications, no one can eavesdrop on someone else's.
|
| Sanctum auth is applied at the route level (see api.php's
| Broadcast::routes() call inside auth:sanctum), so $user is already the
| logged-in User instance when this closure runs.
|
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});
