<?php

namespace Database\Factories;

use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::now()->addDays(fake()->numberBetween(1, 30));
        $reservationEndsAt = $startsAt->copy()->subHours(fake()->numberBetween(1, 24));
        
        return [
            'room_id' => Room::factory(),
            'name' => fake()->words(3, true),
            'max_tickets' => fake()->numberBetween(50, 500),
            'reservation_ends_at' => $reservationEndsAt,
            'starts_at' => $startsAt,
            'tickets' => 0,
        ];
    }
}