<?php

use NativeBlade\Facades\NativeBlade;

beforeEach(function () {
    NativeBlade::forget('auth.user');
});

it('shows the login screen', function () {
    $this->get('/login')->assertOk();
});

it('redirects the dashboard to login when unauthenticated', function () {
    $this->get('/')->assertRedirect(route('login'));
});
