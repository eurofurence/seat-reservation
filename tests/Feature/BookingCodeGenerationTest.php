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

class BookingCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected $regularUser;

    protected $adminUser;

    protected $event;

    protected $seats;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->regularUser = User::factory()->create(['is_admin' => false]);
        $this->adminUser = User::factory()->create(['is_admin' => true]);

        // Create room structure
        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $this->seats = Seat::factory()->count(10)->create(['row_id' => $row->id]);

        // Create event
        $this->event = Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(7),
            'reservation_ends_at' => Carbon::now()->addHours(2),
            'max_tickets' => 100,
        ]);
    }

    /** @test */
    public function regular_user_gets_booking_code_when_creating_booking()
    {
        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
        ];

        $response = $this->actingAs($this->regularUser)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        // Should redirect to confirmation page
        $response->assertRedirect();
        $this->assertStringContainsString('bookings/confirmed', $response->headers->get('Location'));

        // Verify booking has code
        $booking = Booking::where('event_id', $this->event->id)
            ->where('user_id', $this->regularUser->id)
            ->first();

        $this->assertNotNull($booking, 'Booking should be created');
        $this->assertNotNull($booking->booking_code, 'Booking code should be generated');
        $this->assertEquals(3, strlen($booking->booking_code), 'Booking code should be 3 characters');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}$/', $booking->booking_code, 'Booking code should be alphanumeric uppercase');
        $this->assertEquals('online', $booking->type);
    }

    /** @test */
    public function admin_user_gets_booking_code_through_user_interface()
    {
        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'Admin Booking', 'comment' => null],
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        // Should redirect to confirmation page (same as regular user)
        $response->assertRedirect();
        $this->assertStringContainsString('bookings/confirmed', $response->headers->get('Location'));

        // Verify booking HAS code (user interface always gets codes)
        $booking = Booking::where('event_id', $this->event->id)
            ->where('user_id', $this->adminUser->id)
            ->first();

        $this->assertNotNull($booking, 'Booking should be created');
        $this->assertNotNull($booking->booking_code, 'Admin using user interface should get booking code');
        $this->assertEquals(3, strlen($booking->booking_code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}$/', $booking->booking_code);
        $this->assertEquals('online', $booking->type);
    }

    /** @test */
    public function multiple_seats_same_booking_get_same_booking_code()
    {
        $seatData = [
            ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
            ['seat_id' => $this->seats[1]->id, 'name' => 'Jane Doe', 'comment' => 'VIP'],
        ];

        $response = $this->actingAs($this->regularUser)
            ->post(route('bookings.store', $this->event), [
                'seats' => $seatData,
            ]);

        $response->assertRedirect();

        // Get all bookings for this user and event
        $bookings = Booking::where('event_id', $this->event->id)
            ->where('user_id', $this->regularUser->id)
            ->get();

        $this->assertCount(2, $bookings, 'Should create 2 bookings');

        // Both should have the same booking code
        $this->assertNotNull($bookings[0]->booking_code);
        $this->assertNotNull($bookings[1]->booking_code);
        $this->assertEquals($bookings[0]->booking_code, $bookings[1]->booking_code, 'Both seats should have same booking code');
    }

    /** @test */
    public function different_bookings_get_different_booking_codes()
    {
        // First booking
        $this->actingAs($this->regularUser)
            ->post(route('bookings.store', $this->event), [
                'seats' => [
                    ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
                ],
            ]);

        // Create another event for second booking
        $otherEvent = Event::factory()->create([
            'room_id' => $this->event->room_id,
            'starts_at' => Carbon::now()->addDays(14),
            'reservation_ends_at' => Carbon::now()->addHours(3),
            'max_tickets' => 100,
        ]);

        // Second booking
        $this->actingAs($this->regularUser)
            ->post(route('bookings.store', $otherEvent), [
                'seats' => [
                    ['seat_id' => $this->seats[1]->id, 'name' => 'John Doe', 'comment' => null],
                ],
            ]);

        $firstBooking = Booking::where('event_id', $this->event->id)->first();
        $secondBooking = Booking::where('event_id', $otherEvent->id)->first();

        $this->assertNotNull($firstBooking->booking_code);
        $this->assertNotNull($secondBooking->booking_code);
        $this->assertNotEquals($firstBooking->booking_code, $secondBooking->booking_code, 'Different bookings should have different codes');
    }

    /** @test */
    public function booking_code_generation_handles_existing_codes()
    {
        // Create a booking with code 'AA'
        Booking::factory()->create([
            'booking_code' => 'AA',
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[9]->id,
        ]);

        // Now create a booking that should get a different code
        $response = $this->actingAs($this->regularUser)
            ->post(route('bookings.store', $this->event), [
                'seats' => [
                    ['seat_id' => $this->seats[0]->id, 'name' => 'John Doe', 'comment' => null],
                ],
            ]);

        $response->assertRedirect();

        $booking = Booking::where('event_id', $this->event->id)
            ->where('user_id', $this->regularUser->id)
            ->first();

        $this->assertNotNull($booking->booking_code);
        $this->assertNotEquals('AA', $booking->booking_code, 'Should generate different code than existing AA');
    }

    /** @test */
    public function booking_code_is_returned_in_index_controller()
    {
        // Create a booking with code
        Booking::factory()->create([
            'user_id' => $this->regularUser->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
            'booking_code' => 'B5',
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('bookings.index'));

        $response->assertOk();

        // Get the bookings data from the Inertia response
        $props = $response->getOriginalContent()->getData()['page']['props'];
        $bookings = $props['bookings'] ?? null;

        $this->assertNotNull($bookings, 'Bookings should be returned');

        // Handle different possible structures of paginated data
        $bookingItems = null;
        if (is_object($bookings) && method_exists($bookings, 'items')) {
            $bookingItems = $bookings->items();
        } elseif (is_object($bookings) && isset($bookings->data)) {
            $bookingItems = $bookings->data;
        } elseif (is_array($bookings) && isset($bookings['data'])) {
            $bookingItems = $bookings['data'];
        } elseif (is_array($bookings)) {
            $bookingItems = $bookings;
        }

        $this->assertNotNull($bookingItems, 'Could not extract booking items from response');
        $this->assertNotEmpty($bookingItems, 'Should have at least one booking');

        $firstBooking = is_array($bookingItems) ? $bookingItems[0] : $bookingItems[0];
        $bookingCode = is_object($firstBooking) ? $firstBooking->booking_code : $firstBooking['booking_code'];

        $this->assertEquals('B5', $bookingCode, 'Booking code should be included in response');
    }

    /** @test */
    public function booking_code_is_returned_in_show_controller()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->regularUser->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
            'booking_code' => 'C7',
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('bookings.show', [$this->event, $booking]));

        $response->assertOk();

        // The booking should include the booking_code
        $props = $response->getOriginalContent()->getData()['page']['props'];
        $bookingData = $props['booking'] ?? null;

        $this->assertNotNull($bookingData, 'Booking should be returned');
        $this->assertEquals('C7', $bookingData->booking_code ?? $bookingData['booking_code'], 'Booking code should be included in show response');
    }

    /** @test */
    public function booking_code_uniqueness_stress_test()
    {
        $generatedCodes = [];
        $duplicateCount = 0;

        // Generate 100 booking codes and check for duplicates
        for ($i = 0; $i < 100; $i++) {
            $response = $this->actingAs($this->regularUser)
                ->post(route('bookings.store', $this->event), [
                    'seats' => [
                        ['seat_id' => $this->seats[$i % count($this->seats)]->id, 'name' => "User $i", 'comment' => null],
                    ],
                ]);

            if ($i === 0) {
                // First one should redirect to confirmation
                $response->assertRedirect();
            } else {
                // Subsequent ones might fail due to seat conflicts, that's ok for this test
                // We just want to test the code generation logic
            }

            // Create additional event for each booking to avoid seat conflicts
            if ($i > 0) {
                $newEvent = Event::factory()->create([
                    'room_id' => $this->event->room_id,
                    'starts_at' => Carbon::now()->addDays(7 + $i),
                    'reservation_ends_at' => Carbon::now()->addHours(2 + $i),
                    'max_tickets' => 100,
                ]);

                $this->actingAs($this->regularUser)
                    ->post(route('bookings.store', $newEvent), [
                        'seats' => [
                            ['seat_id' => $this->seats[$i % count($this->seats)]->id, 'name' => "User $i", 'comment' => null],
                        ],
                    ]);
            }
        }

        // Count unique booking codes
        $allBookings = Booking::whereNotNull('booking_code')->get();
        $codes = $allBookings->pluck('booking_code')->toArray();
        $uniqueCodes = array_unique($codes);

        // Should have high uniqueness rate (allowing for some theoretical collisions in a small sample)
        $uniqueRate = count($uniqueCodes) / count($codes);
        $this->assertGreaterThan(0.8, $uniqueRate, 'Should have high uniqueness rate for booking codes');
    }

    /** @test */
    public function booking_without_code_does_not_break_display()
    {
        // Create booking without booking code (like admin booking)
        $booking = Booking::factory()->create([
            'user_id' => $this->regularUser->id,
            'event_id' => $this->event->id,
            'seat_id' => $this->seats[0]->id,
            'booking_code' => null, // Explicitly null
        ]);

        // Should not break the index page
        $response = $this->actingAs($this->regularUser)
            ->get(route('bookings.index'));
        $response->assertOk();

        // Should not break the show page
        $response = $this->actingAs($this->regularUser)
            ->get(route('bookings.show', [$this->event, $booking]));
        $response->assertOk();
    }

    /** @test */
    public function booking_code_format_validation()
    {
        // Test that generated codes match expected format
        $codes = [];

        for ($i = 0; $i < 20; $i++) {
            $event = Event::factory()->create([
                'room_id' => $this->event->room_id,
                'starts_at' => Carbon::now()->addDays(7 + $i),
                'reservation_ends_at' => Carbon::now()->addHours(2),
                'max_tickets' => 100,
            ]);

            $response = $this->actingAs($this->regularUser)
                ->post(route('bookings.store', $event), [
                    'seats' => [
                        ['seat_id' => $this->seats[0]->id, 'name' => "User $i", 'comment' => null],
                    ],
                ]);

            if ($response->status() === 302) { // Redirect means success
                $booking = Booking::where('event_id', $event->id)->first();
                if ($booking && $booking->booking_code) {
                    $codes[] = $booking->booking_code;
                }
            }
        }

        $this->assertNotEmpty($codes, 'Should have generated some booking codes');

        foreach ($codes as $code) {
            $this->assertEquals(3, strlen($code), "Code '$code' should be exactly 3 characters");
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}$/', $code, "Code '$code' should only contain uppercase letters and numbers");
        }
    }
}
