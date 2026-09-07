<?php

use Illuminate\Support\Facades\Broadcast;

/**
 * Every private-channel authorization callback for the app lives here.
 * M7 only needs the one per-user notification channel today — a
 * Notification's toBroadcast() targets `App.Models.User.{id}` (Laravel's
 * default private-channel naming for a Notifiable model), and this is
 * the matching authorization rule Echo's subscribe request is checked
 * against.
 */
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
