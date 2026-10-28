<?php

namespace Database\Factories;

use App\Models\Row;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->numberBetween(1, 50);

        return [
            'row_id' => Row::factory(),
            'number' => $number,
            'label' => 'A'.$number,
        ];
    }
}
