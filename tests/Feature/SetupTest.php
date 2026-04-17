<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['setup.gate_enabled' => true]);
});

afterEach(function () {
    config(['setup.gate_enabled' => false]);
    if (File::exists($p = config('setup.lock_path'))) {
        File::delete($p);
    }
});

it('redirects to setup when the lock file is missing', function () {
    if (File::exists($p = config('setup.lock_path'))) {
        File::delete($p);
    }

    $this->get('/')->assertRedirect(route('setup'));
});

it('redirects setup to login when already complete', function () {
    File::put(config('setup.lock_path'), now()->toIso8601String()."\n");

    $this->get(route('setup'))->assertRedirect(route('login'));
});
