<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventBookingWindowUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_moving_booking_starts_at_to_the_future_blocks_further_booking()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seats = Seat::factory()->count(5)->create(['row_id' => $row->id]);

        $event = Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(7),
            'reservation_ends_at' => Carbon::now()->addDays(3),
            'booking_starts_at' => Carbon::now()->subDay(),
            'max_tickets' => 100,
        ]);

        // Booking works while booking_starts_at is in the past.
        $this->actingAs($user)->post(route('bookings.store', $event), [
            'seats' => [['seat_id' => $seats[0]->id, 'name' => 'John Doe', 'comment' => null]],
        ]);
        $this->assertDatabaseCount('bookings', 1);

        // Admin moves booking_starts_at to the future.
        $this->actingAs($admin)->put(route('admin.events.update', $event->id), [
            'name' => $event->name,
            'room_id' => $room->id,
            'starts_at' => $event->starts_at->toDateTimeString(),
            'reservation_ends_at' => $event->reservation_ends_at->toDateTimeString(),
            'booking_starts_at' => Carbon::now()->addHour()->toDateTimeString(),
            'max_tickets' => $event->max_tickets,
        ]);
        $event->refresh();
        $this->assertFalse($event->isBookingOpen());

        // Further booking submission is now blocked, but the seat layout is still viewable.
        $this->actingAs($user)->get(route('bookings.create', $event))->assertOk();

        $response = $this->actingAs($user)->post(route('bookings.store', $event), [
            'seats' => [['seat_id' => $seats[1]->id, 'name' => 'Jane Doe', 'comment' => null]],
        ]);
        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'Booking for this event is not yet open.');

        $this->assertDatabaseCount('bookings', 1);
    }
}
