<?php

it('redirects the root to the public scan page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('scan.index'));
});
