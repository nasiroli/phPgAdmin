<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/login', 'pages::login')->name('login');

Route::livewire('/setup', 'pages::setup')->name('setup');

Route::livewire('/', 'pages::dashboard');
Route::livewire('/servers/create', 'pages::server-form');
Route::livewire('/servers/{server}/edit', 'pages::server-form');
Route::livewire('/connections/create', 'pages::connection-form');
Route::livewire('/connections/{connection}/edit', 'pages::connection-form');
Route::livewire('/explorer/{connection}', 'pages::workspace')->name('explorer');
