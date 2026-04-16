<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/login', 'pages::login')->name('login');

Route::middleware('nb.auth')->group(function () {
    Route::livewire('/', 'pages::dashboard');
    Route::livewire('/servers/create', 'pages::server-form');
    Route::livewire('/servers/{server}/edit', 'pages::server-form');
    Route::livewire('/connections/create', 'pages::connection-form');
    Route::livewire('/connections/{connection}/edit', 'pages::connection-form');
    Route::livewire('/explorer/{connection}', 'pages::workspace')->name('explorer');
});
