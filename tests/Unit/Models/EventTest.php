<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\Room;
use App\Models\Block;
use App\Models\Row;
use App\Models\Seat;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function event_calculates_tickets_left_with_max_tickets_set()
    {
        $room = Room::factory()->create();
        $event = Event::factory()->create([
            'room_id' => $room->id,
            'max_tickets' => 10,
        ]);

        // Create some seats and bookings
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id]);
        $seat3 = Seat::factory()->create(['row_id' => $row->id]);

        // Create 3 bookings
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat1->id]);
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat2->id]);
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat3->id]);

        // Should have 7 tickets left (10 - 3)
        $this->assertEquals(7, $event->tickets_left);
    }

    /** @test */
    public function event_calculates_tickets_left_using_seat_count_when_no_max_tickets()
    {
        $room = Room::factory()->create();
        $event = Event::factory()->create([
            'room_id' => $room->id,
            'max_tickets' => null,
        ]);

        // Create 5 seats
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id]);
        $seat3 = Seat::factory()->create(['row_id' => $row->id]);
        $seat4 = Seat::factory()->create(['row_id' => $row->id]);
        $seat5 = Seat::factory()->create(['row_id' => $row->id]);

        // Create 2 bookings
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat1->id]);
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat2->id]);

        // Should have 3 tickets left (5 seats - 2 bookings)
        $this->assertEquals(3, $event->tickets_left);
    }

    /** @test */
    public function event_returns_zero_tickets_left_when_overbooked()
    {
        $room = Room::factory()->create();
        $event = Event::factory()->create([
            'room_id' => $room->id,
            'max_tickets' => 2, // Only 2 tickets allowed
        ]);

        // Create 3 seats and 3 bookings (more than max_tickets)
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id]);
        $seat3 = Seat::factory()->create(['row_id' => $row->id]);

        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat1->id]);
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat2->id]);
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat3->id]);

        // Should return 0, not negative
        $this->assertEquals(0, $event->tickets_left);
    }

    /** @test */
    public function event_uses_tickets_field_as_fallback_for_max_tickets()
    {
        $room = Room::factory()->create();
        $event = Event::factory()->create([
            'room_id' => $room->id,
            'max_tickets' => null,
            'tickets' => 8, // Fallback to tickets field
        ]);

        // Create seats and bookings
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id]);

        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat1->id]);

        // Should have 7 tickets left (8 - 1)
        $this->assertEquals(7, $event->tickets_left);
    }
}