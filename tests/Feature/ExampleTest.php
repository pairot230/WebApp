<?php

test('home redirects guests to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
