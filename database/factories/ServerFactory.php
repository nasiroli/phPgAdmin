<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'  => fake()->words(2, true).' PG',
            'host'  => fake()->ipv4(),
            'port'  => 5432,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
