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

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $admin;

    protected $event;

    protected $room;

    protected $seats;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create(['is_admin' => false]);
        $this->admin = User::factory()->create(['is_admin' => true]);

        // Create room structure
        $this->room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $this->room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $this->seats = Seat::factory()->count(5)->create(['row_id' => $row->id]);

        // Create event
        $this->event = Event::factory()->create([
            'room_id' => $this->room->id,
            'starts_at' => Carbon::now()->addDays(7),
            'reservation_ends_at' => Carbon::now()->addHours(2),
            'max_tickets' => 100,
        ]);
    }

    /** @test */
    public function user_can_view_their_bookings_index()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats->first()->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('bookings.index'));

        $response->assertOk();
    }

    /** @test */
    public function user_can_view_create_booking_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('bookings.create', $this->event));

        $response->assertOk();
    }

    /** @test */
    public function user_can_create_booking_with_booking_code()
    {
        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => 'Test comment'],
            ['seat_id' => $this->seats[1]->id, 'name' => 'Jane Doe', 'comment' => null],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        // Should redirect to confirmation page
        $response->assertRedirect();
        $this->assertStringContainsString('bookings/confirmed', $response->headers->get('Location'));

        // Verify bookings were created
        $this->assertDatabaseCount('bookings', 2);

        $bookings = Booking::where('event_id', $this->event->id)->get();
        $this->assertTrue($bookings->every(fn ($booking) => $booking->booking_code !== null));
        $this->assertTrue($bookings->every(fn ($booking) => strlen($booking->booking_code) === 2));
        $this->assertTrue($bookings->every(fn ($booking) => $booking->user_id === $this->user->id));
        $this->assertTrue($bookings->every(fn ($booking) => $booking->type === 'online'));

        // All bookings should have the same booking code
        $this->assertEquals(1, $bookings->pluck('booking_code')->unique()->count());
    }

    /** @test */
    public function admin_can_create_booking_with_booking_code()
    {
        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'Guest Name', 'comment' => 'Admin booking'],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        // Should redirect to confirmation page with booking code
        $response->assertRedirect();

        // Admin users through user interface also get booking codes
        $booking = Booking::where('user_id', $this->admin->id)->first();
        $this->assertNotNull($booking->booking_code, 'Admin should get booking code through user interface');
        $this->assertEquals(3, strlen($booking->booking_code), 'Booking code should be 3 characters');
    }

    /** @test */
    public function user_cannot_book_more_than_two_seats_per_event()
    {
        // Create existing bookings for user
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[1]->id,
        ]);

        $seatData = [
            ['seat_id' => $this->seats[2]->id, 'name' => 'John Doe', 'comment' => null],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        $response->assertRedirect()
            ->assertSessionHas('message', 'You can only book a maximum of 2 seats per event.');

        $this->assertDatabaseCount('bookings', 2); // No new booking created
    }

    /** @test */
    public function user_cannot_book_already_booked_seats()
    {
        // Create existing booking
        Booking::factory()->create([
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        $response->assertSessionHasErrors(['seats']);
    }

    /** @test */
    public function user_cannot_book_after_reservation_deadline()
    {
        $this->event->update([
            'reservation_ends_at' => Carbon::now()->subHour(),
        ]);

        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        $response->assertRedirect()
            ->assertSessionHas('message', 'The reservation period for this event has ended.');
    }

    /** @test */
    public function user_cannot_book_when_no_tickets_left()
    {
        $this->event->update(['max_tickets' => 1]);

        // Create existing booking to fill capacity
        Booking::factory()->create([
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        $seatData = [
            ['seat_id' => $this->seats[1]->id, 'name' => 'John Doe', 'comment' => null],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        $response->assertRedirect()
            ->assertSessionHas('message', 'Not enough tickets available for this event.');
    }

    /** @test */
    public function booking_code_is_generated_for_users()
    {
        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        $response->assertRedirect();
        $booking = Booking::where('event_id', $this->event->id)->first();
        $this->assertNotNull($booking->booking_code);
        $this->assertEquals(2, strlen($booking->booking_code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{2}$/', $booking->booking_code);
    }

    /** @test */
    public function booking_codes_are_unique_within_session()
    {
        // Create first booking
        $seatData1 = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
        ];

        $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData1,
            ]);

        $firstBookingCode = Booking::where('event_id', $this->event->id)->first()->booking_code;

        // Create another event and booking
        $otherEvent = Event::factory()->create([
            'room_id' => $this->room->id,
            'starts_at' => Carbon::now()->addDays(14),
            'reservation_ends_at' => Carbon::now()->addHours(2),
            'max_tickets' => 100,
        ]);

        $seatData2 = [
            ['seat_id' => $this->seats[1]->id, 'name' => 'Jane Doe', 'comment' => null],
        ];

        $this->actingAs($this->user)
            ->post(route('bookings.store', $otherEvent), [
                'seats' => $seatData2,
            ]);

        $secondBookingCode = Booking::where('event_id', $otherEvent->id)->first()->booking_code;

        // Booking codes should be different (session unique)
        $this->assertNotEquals($firstBookingCode, $secondBookingCode);
        $this->assertEquals(2, strlen($firstBookingCode));
        $this->assertEquals(2, strlen($secondBookingCode));
    }

    /** @test */
    public function user_can_view_individual_booking()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('bookings.show', [$this->event, $booking]));

        $response->assertOk();
    }

    /** @test */
    public function user_cannot_view_other_users_booking()
    {
        $otherUser = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $otherUser->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('bookings.show', [$this->event, $booking]));

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'You are not authorized to view this booking.');
    }

    /** @test */
    public function user_can_update_their_booking()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
            'name' => 'Old Name',
            'comment' => 'Old Comment',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('bookings.update', [$this->event, $booking]), [
                'name' => 'New Name',
                'comment' => 'New Comment',
            ]);

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'Booking updated!');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'name' => 'New Name',
            'comment' => 'New Comment',
        ]);
    }

    /** @test */
    public function user_cannot_update_other_users_booking()
    {
        $otherUser = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $otherUser->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('bookings.update', [$this->event, $booking]), [
                'name' => 'Hacked Name',
            ]);

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to update this booking!');
    }

    /** @test */
    public function user_can_delete_their_booking()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('bookings.destroy', [$this->event, $booking]));

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'Booking cancelled!');

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    /** @test */
    public function user_cannot_delete_other_users_booking()
    {
        $otherUser = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $otherUser->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('bookings.destroy', [$this->event, $booking]));

        $response->assertRedirect(route('bookings.index'))
            ->assertSessionHas('message', 'You are not allowed to cancel this booking!');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    /** @test */
    public function booking_validation_requires_valid_seats()
    {
        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => [
                    ['seat_id' => 99999, 'name' => 'John Doe', 'comment' => null],
                ],
            ]);

        $response->assertSessionHasErrors(['seats.0.seat_id']);
    }

    /** @test */
    public function booking_validation_requires_seat_names()
    {
        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => [
                    ['seat_id' => $this->seats[0]->id, 'name' => '', 'comment' => null],
                ],
            ]);

        $response->assertSessionHasErrors(['seats.0.name']);
    }

    /** @test */
    public function booking_update_validation_works_correctly()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('bookings.update', [$this->event, $booking]), [
                'name' => '', // Invalid: empty name
                'comment' => str_repeat('a', 300), // Invalid: too long
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function guests_cannot_access_booking_routes()
    {
        $booking = Booking::factory()->create([
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
        ]);

        // Test various routes
        $routes = [
            ['GET', route('bookings.index')],
            ['GET', route('bookings.create', $this->event)],
            ['POST', route('bookings.store', $this->event)],
            ['GET', route('bookings.show', [$this->event, $booking])],
            ['PUT', route('bookings.update', [$this->event, $booking])],
            ['DELETE', route('bookings.destroy', [$this->event, $booking])],
            ['GET', route('bookings.confirmed', [$this->event, 'A7'])],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $this->call($method, $url);
            $response->assertRedirect('/auth/login');
        }
    }

    /** @test */
    public function booking_code_generation_handles_collisions()
    {
        // Create a booking with code 'AA'
        Booking::factory()->create(['booking_code' => 'AA']);

        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        $response->assertRedirect();

        $newBooking = Booking::where('event_id', $this->event->id)->first();
        $this->assertNotEquals('AA', $newBooking->booking_code);
        $this->assertNotNull($newBooking->booking_code);
        $this->assertEquals(2, strlen($newBooking->booking_code));
    }
}
