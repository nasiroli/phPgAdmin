<?php

use App\Models\Connection;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists server and connection profiles', function () {
    $server = Server::factory()->create([
        'name' => 'Local',
        'host' => '127.0.0.1',
        'port' => 5432,
    ]);

    Connection::factory()->create([
        'server_id' => $server->id,
        'database' => 'postgres',
        'username' => 'u',
    ]);

    expect(Server::query()->count())->toBe(1)
        ->and(Connection::query()->count())->toBe(1)
        ->and(Connection::first()->server->is($server))->toBeTrue();
});
