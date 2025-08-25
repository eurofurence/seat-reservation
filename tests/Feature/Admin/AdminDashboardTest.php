<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Event;
use App\Models\Room;
use App\Models\Block;
use App\Models\Row;
use App\Models\Seat;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $user;
    protected $event;
    protected $booking;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin and regular user
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create(['is_admin' => false]);
        
        // Create room structure
        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);
        
        // Create event and booking with booking code
        $this->event = Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(7),
            'reservation_ends_at' => Carbon::now()->addHours(2),
            'max_tickets' => 100
        ]);
        
        $this->booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'seat_id' => $seat->id,
            'booking_code' => 'A7',
            'type' => 'online'
        ]);
    }

    /** @test */
    public function admin_can_view_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'));

        $response->assertOk();
    }

    /** @test */
    public function admin_can_lookup_booking_code_and_redirect_to_event()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.lookup-booking-code'), [
                'booking_code' => 'A7'
            ]);

        $response->assertRedirect(route('admin.events.show', [
            'event' => $this->event->id,
            'bookingcode' => 'A7'
        ]));
    }

    /** @test */
    public function admin_receives_error_for_invalid_booking_code()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.lookup-booking-code'), [
                'booking_code' => 'ZZ'
            ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['booking_code' => 'No booking found with this code.']);
    }

    /** @test */
    public function booking_code_lookup_requires_exact_two_character_format()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.lookup-booking-code'), [
                'booking_code' => 'A'
            ]);

        $response->assertSessionHasErrors('booking_code');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.lookup-booking-code'), [
                'booking_code' => 'ABC'
            ]);

        $response->assertSessionHasErrors('booking_code');
    }

    /** @test */
    public function booking_code_lookup_is_case_insensitive()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.lookup-booking-code'), [
                'booking_code' => 'a7'
            ]);

        $response->assertRedirect(route('admin.events.show', [
            'event' => $this->event->id,
            'bookingcode' => 'A7'
        ]));
    }

    /** @test */
    public function regular_users_cannot_access_admin_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    /** @test */
    public function regular_users_cannot_use_booking_code_lookup()
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.lookup-booking-code'), [
                'booking_code' => 'A7'
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function guests_cannot_access_admin_dashboard()
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect('/auth/login');
    }

    /** @test */
    public function booking_code_lookup_finds_correct_event_when_multiple_exist()
    {
        // Create another event with a different booking
        $otherRoom = Room::factory()->create();
        $otherBlock = Block::factory()->create(['room_id' => $otherRoom->id]);
        $otherRow = Row::factory()->create(['block_id' => $otherBlock->id]);
        $otherSeat = Seat::factory()->create(['row_id' => $otherRow->id]);
        
        $otherEvent = Event::factory()->create([
            'room_id' => $otherRoom->id,
            'starts_at' => Carbon::now()->addDays(14),
            'reservation_ends_at' => Carbon::now()->addHours(2),
            'max_tickets' => 100
        ]);
        
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $otherEvent->id,
            'seat_id' => $otherSeat->id,
            'booking_code' => 'B3',
            'type' => 'online'
        ]);

        // Lookup first booking code
        $response = $this->actingAs($this->admin)
            ->post(route('admin.lookup-booking-code'), [
                'booking_code' => 'A7'
            ]);

        $response->assertRedirect(route('admin.events.show', [
            'event' => $this->event->id,
            'bookingcode' => 'A7'
        ]));

        // Lookup second booking code
        $response = $this->actingAs($this->admin)
            ->post(route('admin.lookup-booking-code'), [
                'booking_code' => 'B3'
            ]);

        $response->assertRedirect(route('admin.events.show', [
            'event' => $otherEvent->id,
            'bookingcode' => 'B3'
        ]));
    }
}