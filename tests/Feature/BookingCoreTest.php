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
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookingCoreTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function admin_can_create_manual_booking()
    {
        // Create authenticated admin user
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        // Create room structure
        $room = Room::create(['name' => 'Test Room']);
        $event = Event::create([
            'name' => 'Test Event',
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);

        $block = Block::create([
            'room_id' => $room->id,
            'name' => 'Test Block',
            'type' => 'seating',
        ]);

        $row = Row::create([
            'block_id' => $block->id,
            'name' => 'Row A',
            'order' => 1,
        ]);

        $seat1 = Seat::create([
            'row_id' => $row->id,
            'label' => 'A1',
            'number' => 1,
        ]);

        $seat2 = Seat::create([
            'row_id' => $row->id,
            'label' => 'A2',
            'number' => 2,
        ]);

        // Test manual booking
        $response = $this->post(route('admin.events.manual-booking', $event->id), [
            'guest_name' => 'John Doe',
            'comment' => 'VIP guest',
            'seat_ids' => [$seat1->id, $seat2->id],
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success');

        // Verify bookings were created
        $this->assertDatabaseHas('bookings', [
            'event_id' => $event->id,
            'seat_id' => $seat1->id,
            'name' => 'John Doe',
            'comment' => 'VIP guest',
            'type' => 'admin',
            'user_id' => null,
        ]);

        $this->assertEquals(2, Booking::where('event_id', $event->id)->count());
    }

    /** @test */
    public function manual_booking_requires_guest_name()
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $room = Room::create(['name' => 'Test Room']);
        $event = Event::create([
            'name' => 'Test Event',
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1),
        ]);

        $response = $this->post(route('admin.events.manual-booking', $event->id), [
            'guest_name' => '',
            'seat_ids' => [1], // Dummy seat ID for validation
        ]);

        $response->assertSessionHasErrors(['guest_name']);
    }

    /** @test */
    public function cannot_double_book_same_seat()
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $room = Room::create(['name' => 'Test Room']);
        $event = Event::create([
            'name' => 'Test Event',
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1),
        ]);

        $block = Block::create([
            'room_id' => $room->id,
            'name' => 'Test Block',
            'type' => 'seating',
        ]);

        $row = Row::create([
            'block_id' => $block->id,
            'name' => 'Row A',
        ]);

        $seat = Seat::create([
            'row_id' => $row->id,
            'label' => 'A1',
            'number' => 1,
        ]);

        // Create first booking
        Booking::create([
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'name' => 'First Guest',
            'type' => 'admin',
        ]);

        // Try to book the same seat again
        $response = $this->post(route('admin.events.manual-booking', $event->id), [
            'guest_name' => 'Second Guest',
            'seat_ids' => [$seat->id],
        ]);

        $response->assertRedirect()
            ->assertSessionHas('error', 'One or more selected seats are already booked.');

        // Verify only one booking exists
        $this->assertEquals(1, Booking::count());
    }
}
