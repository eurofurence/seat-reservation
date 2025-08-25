<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Event;
use App\Models\Room;
use App\Models\Booking;
use App\Models\Block;
use App\Models\Row;
use App\Models\Seat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class EventAdminController2Test extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $admin;
    private $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->room = Room::factory()->create();
    }

    /** @test */
    public function admin_can_view_event_index_with_events()
    {
        $this->actingAs($this->admin);
        
        $event1 = Event::factory()->create(['room_id' => $this->room->id, 'name' => 'Event 1']);
        $event2 = Event::factory()->create(['room_id' => $this->room->id, 'name' => 'Event 2']);

        // Create some bookings to test the booking count
        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id]);
        
        Booking::factory()->create(['event_id' => $event1->id, 'seat_id' => $seat1->id]);
        Booking::factory()->create(['event_id' => $event2->id, 'seat_id' => $seat2->id]);

        $response = $this->get(route('admin.events.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/EventIndex')
            ->has('events', 2)
            ->has('rooms')
            ->where('title', 'Events')
        );
    }

    /** @test */
    public function admin_can_create_event_with_all_fields()
    {
        $this->actingAs($this->admin);

        $eventData = [
            'name' => 'Test Event',
            'room_id' => $this->room->id,
            'starts_at' => Carbon::now()->addDays(1)->toDateTimeString(),
            'reservation_ends_at' => Carbon::now()->addHours(2)->toDateTimeString(),
            'max_tickets' => 100,
        ];

        $response = $this->post(route('admin.events.store'), $eventData);

        $response->assertRedirect(route('admin.events.index'));
        $response->assertSessionHas('success', 'Event created successfully');
        
        $this->assertDatabaseHas('events', [
            'name' => 'Test Event',
            'room_id' => $this->room->id,
            'max_tickets' => 100,
        ]);
    }

    /** @test */
    public function admin_can_create_event_with_minimal_fields()
    {
        $this->actingAs($this->admin);

        $eventData = [
            'name' => 'Minimal Event',
            'room_id' => $this->room->id,
        ];

        $response = $this->post(route('admin.events.store'), $eventData);

        $response->assertRedirect(route('admin.events.index'));
        $response->assertSessionHas('success', 'Event created successfully');
        
        $this->assertDatabaseHas('events', [
            'name' => 'Minimal Event',
            'room_id' => $this->room->id,
        ]);
    }

    /** @test */
    public function admin_can_update_event_details()
    {
        $this->actingAs($this->admin);
        $event = Event::factory()->create(['room_id' => $this->room->id, 'name' => 'Old Name']);

        $updateData = [
            'name' => 'Updated Event Name',
            'room_id' => $this->room->id,
            'max_tickets' => 150,
        ];

        $response = $this->put(route('admin.events.update', $event->id), $updateData);

        $response->assertRedirect(route('admin.events.index'));
        $response->assertSessionHas('success', 'Event updated successfully');
        
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Updated Event Name',
            'max_tickets' => 150,
        ]);
    }

    /** @test */
    public function admin_can_delete_event()
    {
        $this->actingAs($this->admin);
        $event = Event::factory()->create(['room_id' => $this->room->id]);

        $response = $this->delete(route('admin.events.destroy', $event->id));

        $response->assertRedirect(route('admin.events.index'));
        $response->assertSessionHas('success', 'Event deleted successfully');
        
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    /** @test */
    public function event_delete_cascades_to_bookings()
    {
        $this->actingAs($this->admin);
        $event = Event::factory()->create(['room_id' => $this->room->id]);
        
        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);
        
        $booking = Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat->id]);

        $response = $this->delete(route('admin.events.destroy', $event->id));

        $response->assertRedirect(route('admin.events.index'));
        
        // Verify both event and booking are deleted
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    /** @test */
    public function admin_can_view_event_show_page_with_bookings()
    {
        $this->actingAs($this->admin);
        $event = Event::factory()->create(['room_id' => $this->room->id]);

        // Create room structure with stage and seating
        $stageBlock = Block::factory()->stage()->create(['room_id' => $this->room->id]);
        $seatingBlock = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $row = Row::factory()->create(['block_id' => $seatingBlock->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id]);

        // Create some bookings
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat1->id]);
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat2->id]);

        $response = $this->get(route('admin.events.show', $event->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/EventShow')
            ->where('event.id', $event->id)
            ->has('room')
            ->has('blocks')
            ->has('stageBlocks')
            ->has('bookings')
            ->has('bookedSeats', 2) // Should show 2 booked seats
        );
    }

    /** @test */
    public function event_show_includes_booking_search_functionality()
    {
        $this->actingAs($this->admin);
        $event = Event::factory()->create(['room_id' => $this->room->id]);
        
        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id]);
        
        // Create bookings with different names
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat1->id, 'name' => 'John Doe']);
        Booking::factory()->create(['event_id' => $event->id, 'seat_id' => $seat2->id, 'name' => 'Jane Smith']);

        $response = $this->get(route('admin.events.show', $event->id) . '?search=John');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('search', 'John')
        );
    }

    /** @test */
    public function admin_can_export_bookings_as_csv()
    {
        $this->actingAs($this->admin);
        $event = Event::factory()->create(['room_id' => $this->room->id, 'name' => 'Test Event']);
        
        $block = Block::factory()->seating()->create(['room_id' => $this->room->id, 'name' => 'Block A']);
        $row = Row::factory()->create(['block_id' => $block->id, 'name' => 'Row 1']);
        $seat = Seat::factory()->create(['row_id' => $row->id, 'label' => 'A1']);
        
        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'name' => 'John Doe',
            'picked_up_at' => now(),
        ]);

        $response = $this->get(route('admin.events.export', $event->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename="bookings-Test Event-' . date('Y-m-d') . '.csv"');
        
        $content = $response->getContent();
        $this->assertStringContainsString('Test Event', $content);
        $this->assertStringContainsString('John Doe', $content);
        $this->assertStringContainsString('Block A', $content);
        $this->assertStringContainsString('Row 1', $content);
        $this->assertStringContainsString('A1', $content);
    }

    /** @test */
    public function csv_export_handles_special_characters()
    {
        $this->actingAs($this->admin);
        $event = Event::factory()->create(['room_id' => $this->room->id, 'name' => 'Event "With" Quotes']);
        
        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);
        
        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'name' => 'John "Johnny" Doe',
            'comment' => 'Special guest, needs assistance',
            'picked_up_at' => now(),
        ]);

        $response = $this->get(route('admin.events.export', $event->id));

        $response->assertOk();
        
        $content = $response->getContent();
        $this->assertStringContainsString('"Event ""With"" Quotes"', $content);
        $this->assertStringContainsString('"John ""Johnny"" Doe"', $content);
    }

    /** @test */
    public function event_validation_requires_name_and_room()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.events.store'), []);

        $response->assertSessionHasErrors(['name', 'room_id']);
    }

    /** @test */
    public function event_validation_requires_valid_room()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.events.store'), [
            'name' => 'Test Event',
            'room_id' => 999, // Non-existent room
        ]);

        $response->assertSessionHasErrors(['room_id']);
    }

    /** @test */
    public function event_validation_requires_positive_max_tickets()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.events.store'), [
            'name' => 'Test Event',
            'room_id' => $this->room->id,
            'max_tickets' => -1, // Invalid negative value
        ]);

        $response->assertSessionHasErrors(['max_tickets']);
    }

    /** @test */
    public function event_validation_requires_valid_dates()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.events.store'), [
            'name' => 'Test Event',
            'room_id' => $this->room->id,
            'starts_at' => 'invalid-date',
            'reservation_ends_at' => 'also-invalid',
        ]);

        $response->assertSessionHasErrors(['starts_at', 'reservation_ends_at']);
    }

    /** @test */
    public function non_admin_cannot_access_events_routes()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);
        
        $event = Event::factory()->create(['room_id' => $this->room->id]);

        $response = $this->get(route('admin.events.index'));
        $response->assertForbidden();

        $response = $this->post(route('admin.events.store'), []);
        $response->assertForbidden();

        $response = $this->get(route('admin.events.show', $event->id));
        $response->assertForbidden();

        $response = $this->get(route('admin.events.export', $event->id));
        $response->assertForbidden();
    }
}