<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Row;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Row>
 */
class RowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'block_id' => Block::factory(),
            'name' => fake()->randomElement(['Row A', 'Row B', 'Row C', 'Row 1', 'Row 2', 'Row 3']),
            'order' => fake()->numberBetween(1, 20),
            'seats_count' => fake()->numberBetween(10, 50),
            'custom_seat_count' => null,
        ];
    }
}
