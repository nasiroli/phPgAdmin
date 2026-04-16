<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    protected $model = Connection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'database' => 'postgres',
            'username' => fake()->userName(),
            'password' => 'secret',
            'sslmode' => 'prefer',
            'last_error' => null,
            'last_connected_at' => null,
        ];
    }
}
