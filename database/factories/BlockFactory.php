<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    private static int $cell = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Distinct 1x1 cells cycling through the bottom two grid rows (unused by hardcoded test
        // positions), so blocks resubmitted to the layout update never trip the overlap check.
        // ponytail: repeats after 24 blocks; widen the band if a test ever needs more.
        $cell = self::$cell++ % 24;

        return [
            'room_id' => Room::factory(),
            'name' => fake()->randomElement(['Block A', 'Block B', 'Block C', 'Main Floor', 'Balcony']),
            'type' => 'seating',
            'position_x' => 11 - $cell % 12,
            'position_y' => 7 - intdiv($cell, 12),
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
            'rotation' => 0,
        ]);
    }

    public function entrance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'entrance',
            'name' => 'Entrance',
            'position_x' => -1,
            'position_y' => 2,
            'rotation' => 0,
        ]);
    }

    public function comment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'comment',
            'name' => 'Note',
        ]);
    }
}
