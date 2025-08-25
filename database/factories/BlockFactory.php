<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Block>
 */
class BlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'name' => fake()->randomElement(['Block A', 'Block B', 'Block C', 'Main Floor', 'Balcony']),
            'type' => 'seating',
            'position_x' => fake()->numberBetween(0, 10),
            'position_y' => fake()->numberBetween(0, 10),
            'rotation' => fake()->randomElement([0, 90, 180, 270]),
            'order' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the block is a seating block.
     */
    public function seating(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'seating',
            'name' => fake()->randomElement(['Block A', 'Block B', 'Block C', 'Main Floor', 'Balcony']),
        ]);
    }

    /**
     * Indicate that the block is a stage block.
     */
    public function stage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'stage',
            'name' => fake()->randomElement(['Main Stage', 'Side Stage', 'Platform', 'Podium']),
            'rotation' => 0, // Stages typically don't rotate
        ]);
    }
}