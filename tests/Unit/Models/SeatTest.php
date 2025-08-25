<?php

namespace Tests\Unit\Models;

use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function seat_can_check_if_booked_for_event()
    {
        $room = Room::factory()->create();
        $event = Event::factory()->create(['room_id' => $room->id]);
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);

        // Seat should not be booked initially
        $this->assertFalse($seat->isBookedForEvent($event->id));

        // Create booking for this seat and event
        Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat->id,
        ]);

        // Now seat should be booked for this event
        $this->assertTrue($seat->isBookedForEvent($event->id));

        // But not booked for a different event
        $otherEvent = Event::factory()->create(['room_id' => $room->id]);
        $this->assertFalse($seat->isBookedForEvent($otherEvent->id));
    }

    /** @test */
    public function seat_can_generate_full_label()
    {
        $room = Room::factory()->create();
        $block = Block::factory()->seating()->create([
            'room_id' => $room->id,
            'name' => 'Block A',
        ]);
        $row = Row::factory()->create([
            'block_id' => $block->id,
            'name' => 'Row 1',
        ]);
        $seat = Seat::factory()->create([
            'row_id' => $row->id,
            'label' => 'A1',
        ]);

        $fullLabel = $seat->getFullLabel();

        $this->assertEquals('Block A-Row 1-A1', $fullLabel);
    }

    /** @test */
    public function seat_name_accessor_returns_label_when_name_is_null()
    {
        $room = Room::factory()->create();
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);

        // Test the name accessor - should return label since there's no name column
        $seat = Seat::factory()->create([
            'row_id' => $row->id,
            'label' => 'A1',
        ]);

        // The name accessor should return the label
        $this->assertEquals('A1', $seat->name);

        // Test with different label
        $seat2 = Seat::factory()->create([
            'row_id' => $row->id,
            'label' => 'B5',
        ]);

        $this->assertEquals('B5', $seat2->name);
    }
}
