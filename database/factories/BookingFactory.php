<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null, // Can be null for manual bookings
            'event_id' => Event::factory(),
            'seat_id' => Seat::factory(),
            'type' => 'manual',
            'booking_code' => null, // Only set for non-admin bookings
            'name' => fake()->name(),
            'comment' => fake()->optional()->sentence(),
            'picked_up_at' => null,
        ];
    }

    /**
     * Indicate that the booking is for a regular user.
     */
    public function withUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'type' => 'online',
            'booking_code' => strtoupper(fake()->randomLetter() . fake()->randomDigit()),
        ]);
    }

    /**
     * Indicate that the booking is an admin booking.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'type' => 'admin',
        ]);
    }

    /**
     * Indicate that the booking has been picked up.
     */
    public function pickedUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'picked_up_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}