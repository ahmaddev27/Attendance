<?php

use App\Models\User;

it('redirects guests from admin routes', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('allows authenticated users into admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin')->assertOk();
});
