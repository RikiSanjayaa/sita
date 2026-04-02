<?php

use Inertia\Testing\AssertableInertia as Assert;

test('missing routes render the custom inertia 404 page', function () {
    $response = $this->get('/halaman-yang-tidak-ada');

    $response
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 404));
});
