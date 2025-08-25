<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Block;
use App\Models\Row;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected User $admin;
    protected Event $event;
    protected Room $room;
    protected $seats;
    protected Booking $user1Booking;
    protected Booking $user2Booking;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->user1 = User::factory()->create(['is_admin' => false]);
        $this->user2 = User::factory()->create(['is_admin' => false]);
        $this->admin = User::factory()->create(['is_admin' => true]);

        // Create room with seats
        $this->room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $this->room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $this->seats = Seat::factory()->count(5)->create(['row_id' => $row->id]);

        // Create event
        $this->event = Event::factory()->create([
            'room_id' => $this->room->id,
            'reservation_ends_at' => now()->addDays(7),
            'starts_at' => now()->addDays(10),
        ]);

        // Create bookings for each user
        $this->user1Booking = Booking::factory()->create([
            'user_id' => $this->user1->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
            'name' => 'User 1 Booking',
            'booking_code' => 'ABC',
        ]);

        $this->user2Booking = Booking::factory()->create([
            'user_id' => $this->user2->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[1]->id,
            'name' => 'User 2 Booking',
            'comment' => null,
            'booking_code' => 'DEF',
        ]);
    }

    /** @test */
    public function user_cannot_view_another_users_booking()
    {
        $response = $this->actingAs($this->user1)
            ->get(route('bookings.show', [$this->event, $this->user2Booking]));

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'You are not authorized to view this booking.');

        // Ensure user1 can still view their own booking
        $response = $this->actingAs($this->user1)
            ->get(route('bookings.show', [$this->event, $this->user1Booking]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Booking/ShowBooking')
                ->has('booking', fn ($booking) => $booking
                    ->where('id', $this->user1Booking->id)
                    ->where('name', 'User 1 Booking')
                    ->etc()
                )
            );
    }

    /** @test */
    public function user_cannot_update_another_users_booking()
    {
        $response = $this->actingAs($this->user1)
            ->put(route('bookings.update', [$this->event, $this->user2Booking]), [
                'name' => 'Hacked Name',
                'comment' => 'Hacked Comment',
            ]);

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to update this booking!');

        // Verify the booking was not changed
        $this->user2Booking->refresh();
        $this->assertEquals('User 2 Booking', $this->user2Booking->name);
        $this->assertNull($this->user2Booking->comment);
    }

    /** @test */
    public function user_cannot_delete_another_users_booking()
    {
        $response = $this->actingAs($this->user1)
            ->delete(route('bookings.destroy', [$this->event, $this->user2Booking]));

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to cancel this booking!');

        // Verify the booking still exists
        $this->assertDatabaseHas('bookings', [
            'id' => $this->user2Booking->id,
            'user_id' => $this->user2->id,
        ]);
    }

    /** @test */
    public function user_only_sees_their_own_bookings_in_index()
    {
        $response = $this->actingAs($this->user1)
            ->get(route('bookings.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Booking/IndexBooking')
                ->has('bookings.data', 1) // User1 should only see their 1 booking
                ->has('bookings.data.0', fn ($booking) => $booking
                    ->where('id', $this->user1Booking->id)
                    ->where('name', 'User 1 Booking')
                    ->etc()
                )
            );

        // Now check user2 only sees their booking
        $response = $this->actingAs($this->user2)
            ->get(route('bookings.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Booking/IndexBooking')
                ->has('bookings.data', 1) // User2 should only see their 1 booking
                ->has('bookings.data.0', fn ($booking) => $booking
                    ->where('id', $this->user2Booking->id)
                    ->where('name', 'User 2 Booking')
                    ->etc()
                )
            );
    }

    /** @test */
    public function user_cannot_access_another_users_booking_confirmation()
    {
        $response = $this->actingAs($this->user1)
            ->get(route('bookings.confirmed', [$this->event, 'DEF'])); // User2's booking code

        // Should redirect because the booking code doesn't belong to user1
        $response->assertRedirect(route('bookings.index'));

        // User1 can access their own confirmation
        $response = $this->actingAs($this->user1)
            ->get(route('bookings.confirmed', [$this->event, 'ABC']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Booking/BookingConfirmed')
                ->where('bookingCode', 'ABC')
            );
    }

    /** @test */
    public function admin_can_view_any_users_booking()
    {
        // Admin can view user1's booking
        $response = $this->actingAs($this->admin)
            ->get(route('bookings.show', [$this->event, $this->user1Booking]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Booking/ShowBooking')
                ->has('booking', fn ($booking) => $booking
                    ->where('id', $this->user1Booking->id)
                    ->etc()
                )
            );

        // Admin can view user2's booking
        $response = $this->actingAs($this->admin)
            ->get(route('bookings.show', [$this->event, $this->user2Booking]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Booking/ShowBooking')
                ->has('booking', fn ($booking) => $booking
                    ->where('id', $this->user2Booking->id)
                    ->etc()
                )
            );
    }

    /** @test */
    public function admin_can_update_any_users_booking()
    {
        $response = $this->actingAs($this->admin)
            ->put(route('bookings.update', [$this->event, $this->user1Booking]), [
                'name' => 'Admin Updated Name',
                'comment' => 'Admin Note',
            ]);

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'Booking updated!');

        // Verify the booking was changed
        $this->user1Booking->refresh();
        $this->assertEquals('Admin Updated Name', $this->user1Booking->name);
        $this->assertEquals('Admin Note', $this->user1Booking->comment);
    }

    /** @test */
    public function admin_can_delete_any_users_booking()
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('bookings.destroy', [$this->event, $this->user1Booking]));

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'Booking cancelled!');

        // Verify the booking was deleted
        $this->assertDatabaseMissing('bookings', [
            'id' => $this->user1Booking->id,
        ]);
    }

    /** @test */
    public function user_cannot_update_booking_after_reservation_deadline()
    {
        // Set the event reservation deadline to the past
        $this->event->reservation_ends_at = now()->subDay();
        $this->event->save();

        $response = $this->actingAs($this->user1)
            ->put(route('bookings.update', [$this->event, $this->user1Booking]), [
                'name' => 'Updated Name',
            ]);

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to update this booking!');

        // Verify the booking was not changed
        $this->user1Booking->refresh();
        $this->assertEquals('User 1 Booking', $this->user1Booking->name);
    }

    /** @test */
    public function user_cannot_delete_booking_after_reservation_deadline()
    {
        // Set the event reservation deadline to the past
        $this->event->reservation_ends_at = now()->subDay();
        $this->event->save();

        $response = $this->actingAs($this->user1)
            ->delete(route('bookings.destroy', [$this->event, $this->user1Booking]));

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to cancel this booking!');

        // Verify the booking still exists
        $this->assertDatabaseHas('bookings', [
            'id' => $this->user1Booking->id,
        ]);
    }

    /** @test */
    public function user_cannot_update_booking_after_ticket_pickup()
    {
        // Mark the ticket as picked up
        $this->user1Booking->picked_up_at = now();
        $this->user1Booking->save();

        $response = $this->actingAs($this->user1)
            ->put(route('bookings.update', [$this->event, $this->user1Booking]), [
                'name' => 'Updated Name',
            ]);

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to update this booking!');

        // Verify the booking was not changed
        $this->user1Booking->refresh();
        $this->assertEquals('User 1 Booking', $this->user1Booking->name);
    }

    /** @test */
    public function user_cannot_delete_booking_after_ticket_pickup()
    {
        // Mark the ticket as picked up
        $this->user1Booking->picked_up_at = now();
        $this->user1Booking->save();

        $response = $this->actingAs($this->user1)
            ->delete(route('bookings.destroy', [$this->event, $this->user1Booking]));

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to cancel this booking!');

        // Verify the booking still exists
        $this->assertDatabaseHas('bookings', [
            'id' => $this->user1Booking->id,
        ]);
    }

    /** @test */
    public function guest_cannot_access_any_booking_routes()
    {
        // Index
        $response = $this->get(route('bookings.index'));
        $response->assertRedirect(route('auth.login'));

        // Show
        $response = $this->get(route('bookings.show', [$this->event, $this->user1Booking]));
        $response->assertRedirect(route('auth.login'));

        // Update
        $response = $this->put(route('bookings.update', [$this->event, $this->user1Booking]), [
            'name' => 'Guest Update',
        ]);
        $response->assertRedirect(route('auth.login'));

        // Delete
        $response = $this->delete(route('bookings.destroy', [$this->event, $this->user1Booking]));
        $response->assertRedirect(route('auth.login'));

        // Confirmation
        $response = $this->get(route('bookings.confirmed', [$this->event, 'ABC']));
        $response->assertRedirect(route('auth.login'));
    }

    /** @test */
    public function concurrent_users_cannot_interfere_with_each_other()
    {
        // Create a third user
        $user3 = User::factory()->create(['is_admin' => false]);
        
        // User1 and User3 both have bookings
        $user3Booking = Booking::factory()->create([
            'user_id' => $user3->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[2]->id,
            'name' => 'User 3 Booking',
            'booking_code' => 'GHI',
        ]);

        // User1 tries to access User3's booking while User3 is also active
        $response1 = $this->actingAs($this->user1)
            ->get(route('bookings.show', [$this->event, $user3Booking]));

        $response1->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'You are not authorized to view this booking.');

        // User3 can still access their own booking
        $response3 = $this->actingAs($user3)
            ->get(route('bookings.show', [$this->event, $user3Booking]));

        $response3->assertOk();

        // User1 tries to delete User3's booking
        $response1Delete = $this->actingAs($this->user1)
            ->delete(route('bookings.destroy', [$this->event, $user3Booking]));

        $response1Delete->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to cancel this booking!');

        // Verify User3's booking still exists
        $this->assertDatabaseHas('bookings', [
            'id' => $user3Booking->id,
            'user_id' => $user3->id,
        ]);
    }
}