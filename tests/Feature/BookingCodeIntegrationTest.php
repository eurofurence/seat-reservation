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

class BookingCodeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function end_to_end_booking_code_flow_for_regular_user()
    {
        // Create a regular user
        $user = User::factory()->create(['is_admin' => false]);

        // Create room structure
        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seats = Seat::factory()->count(3)->create(['row_id' => $row->id]);

        // Create event
        $event = Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(7),
            'reservation_ends_at' => Carbon::now()->addHours(2),
            'max_tickets' => 100,
        ]);

        // Step 1: Create booking through controller
        $seatData = [
            ['seat_id' => $seats[0]->id, 'name' => 'John Doe', 'comment' => 'Test booking'],
        ];

        $response = $this->actingAs($user)
            ->post(route('bookings.store', $event), [
                'seats' => $seatData,
            ]);

        // Should redirect to confirmation page
        $response->assertRedirect();
        $this->assertStringContainsString('bookings/confirmed', $response->headers->get('Location'));

        // Step 2: Verify booking was created with code
        $booking = Booking::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($booking, 'Booking should be created');
        $this->assertNotNull($booking->booking_code, 'Booking code should be generated for regular user');
        $this->assertEquals(2, strlen($booking->booking_code), 'Booking code should be 2 characters');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{2}$/', $booking->booking_code);
        $this->assertEquals('John Doe', $booking->name);
        $this->assertEquals('Test booking', $booking->comment);
        $this->assertEquals('online', $booking->type);

        // Step 3: Verify index endpoint includes booking code
        $response = $this->actingAs($user)
            ->get(route('bookings.index'));
        $response->assertOk();

        // Step 4: Verify show endpoint includes booking code
        $response = $this->actingAs($user)
            ->get(route('bookings.show', [$event, $booking]));
        $response->assertOk();

        // Step 5: Verify database state
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'user_id' => $user->id,
            'event_id' => $event->id,
            'booking_code' => $booking->booking_code,
            'type' => 'online',
        ]);

        echo "SUCCESS: Regular user booking generated code: {$booking->booking_code}\n";
    }

    /** @test */
    public function end_to_end_booking_code_flow_for_admin_user()
    {
        // Create an admin user
        $admin = User::factory()->create(['is_admin' => true]);

        // Create room structure
        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seats = Seat::factory()->count(3)->create(['row_id' => $row->id]);

        // Create event
        $event = Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(7),
            'reservation_ends_at' => Carbon::now()->addHours(2),
            'max_tickets' => 100,
        ]);

        // Step 1: Create booking through controller as admin
        $seatData = [
            ['seat_id' => $seats[0]->id, 'name' => 'Admin Booking', 'comment' => 'Manual booking'],
        ];

        $response = $this->actingAs($admin)
            ->post(route('bookings.store', $event), [
                'seats' => $seatData,
            ]);

        // Should redirect to confirmation page (same as regular user)
        $response->assertRedirect();
        $this->assertStringContainsString('bookings/confirmed', $response->headers->get('Location'));

        // Step 2: Verify booking was created WITH code (user interface always gets codes)
        $booking = Booking::where('event_id', $event->id)
            ->where('user_id', $admin->id)
            ->first();

        $this->assertNotNull($booking, 'Booking should be created');
        $this->assertNotNull($booking->booking_code, 'Admin using user interface should get booking code');
        $this->assertEquals('Admin Booking', $booking->name);
        $this->assertEquals('Manual booking', $booking->comment);
        $this->assertEquals('online', $booking->type);

        // Step 3: Verify database state
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'user_id' => $admin->id,
            'event_id' => $event->id,
            'booking_code' => $booking->booking_code,
            'type' => 'online',
        ]);

        echo "SUCCESS: Admin user booking through user interface got code: {$booking->booking_code}\n";
    }

    /** @test */
    public function multiple_regular_users_get_different_booking_codes()
    {
        // Create multiple regular users
        $user1 = User::factory()->create(['is_admin' => false, 'name' => 'User One']);
        $user2 = User::factory()->create(['is_admin' => false, 'name' => 'User Two']);

        // Create room structure
        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seats = Seat::factory()->count(5)->create(['row_id' => $row->id]);

        // Create event
        $event = Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(7),
            'reservation_ends_at' => Carbon::now()->addHours(2),
            'max_tickets' => 100,
        ]);

        // User 1 creates booking
        $response1 = $this->actingAs($user1)
            ->post(route('bookings.store', $event), [
                'seats' => [
                    ['seat_id' => $seats[0]->id, 'name' => 'User One', 'comment' => null],
                ],
            ]);
        $response1->assertRedirect();

        // User 2 creates booking
        $response2 = $this->actingAs($user2)
            ->post(route('bookings.store', $event), [
                'seats' => [
                    ['seat_id' => $seats[1]->id, 'name' => 'User Two', 'comment' => null],
                ],
            ]);
        $response2->assertRedirect();

        // Verify both got different codes
        $booking1 = Booking::where('event_id', $event->id)->where('user_id', $user1->id)->first();
        $booking2 = Booking::where('event_id', $event->id)->where('user_id', $user2->id)->first();

        $this->assertNotNull($booking1->booking_code);
        $this->assertNotNull($booking2->booking_code);
        $this->assertNotEquals($booking1->booking_code, $booking2->booking_code);

        echo "SUCCESS: User1 got code '{$booking1->booking_code}', User2 got code '{$booking2->booking_code}'\n";
    }

    /** @test */
    public function booking_code_generation_stress_test()
    {
        $codes = [];
        $users = User::factory()->count(50)->create(['is_admin' => false]);

        // Create room structure
        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seats = Seat::factory()->count(10)->create(['row_id' => $row->id]);

        foreach ($users as $index => $user) {
            // Create separate events to avoid seat conflicts
            $event = Event::factory()->create([
                'room_id' => $room->id,
                'starts_at' => Carbon::now()->addDays(7 + $index),
                'reservation_ends_at' => Carbon::now()->addHours(2),
                'max_tickets' => 100,
            ]);

            $response = $this->actingAs($user)
                ->post(route('bookings.store', $event), [
                    'seats' => [
                        ['seat_id' => $seats[$index % count($seats)]->id, 'name' => "User $index", 'comment' => null],
                    ],
                ]);

            if ($response->status() === 302) { // Success
                $booking = Booking::where('event_id', $event->id)->where('user_id', $user->id)->first();
                if ($booking && $booking->booking_code) {
                    $codes[] = $booking->booking_code;
                }
            }
        }

        $this->assertGreaterThan(40, count($codes), 'Should generate codes for most bookings');

        $uniqueCodes = array_unique($codes);
        $duplicateCount = count($codes) - count($uniqueCodes);

        echo 'Generated '.count($codes).' codes with '.count($uniqueCodes)." unique codes ($duplicateCount duplicates)\n";
        echo 'Sample codes: '.implode(', ', array_slice($codes, 0, 10))."\n";

        // Allow for some duplicates in a large sample, but should be mostly unique
        $uniqueRate = count($uniqueCodes) / count($codes);
        $this->assertGreaterThan(0.7, $uniqueRate, 'Uniqueness rate should be > 70%, got '.round($uniqueRate * 100, 1).'%');
    }

    /** @test */
    public function verify_current_database_state()
    {
        echo "\n=== CURRENT DATABASE STATE ===\n";

        $allBookings = Booking::with('user:id,name,is_admin')
            ->orderBy('id')
            ->get(['id', 'user_id', 'booking_code', 'type', 'created_at']);

        foreach ($allBookings as $booking) {
            $userInfo = $booking->user ? $booking->user->name.' (admin: '.($booking->user->is_admin ? 'YES' : 'NO').')' : 'No user';
            echo "Booking {$booking->id}: User {$booking->user_id} ({$userInfo}), Code: ".($booking->booking_code ?: 'NULL').", Type: {$booking->type}\n";
        }

        // Count bookings by type
        $adminBookings = $allBookings->filter(fn ($b) => $b->user && $b->user->is_admin);
        $regularBookings = $allBookings->filter(fn ($b) => $b->user && ! $b->user->is_admin);
        $noUserBookings = $allBookings->filter(fn ($b) => ! $b->user);

        echo "\nSUMMARY:\n";
        echo 'Total bookings: '.$allBookings->count()."\n";
        echo 'Admin user bookings: '.$adminBookings->count()." (should have codes if through user interface)\n";
        echo 'Regular user bookings: '.$regularBookings->count()." (should have codes)\n";
        echo 'No user bookings: '.$noUserBookings->count()."\n";

        // Verify expectations - All user interface bookings should have codes
        foreach ($adminBookings as $booking) {
            if ($booking->type === 'online') {
                $this->assertNotNull($booking->booking_code, "Admin booking {$booking->id} through user interface should have booking code");
            }
        }

        foreach ($regularBookings as $booking) {
            if ($booking->type === 'online') {
                $this->assertNotNull($booking->booking_code, "Regular user booking {$booking->id} should have booking code");
            }
        }

        $this->assertTrue(true); // Test passes if we get here without assertion failures
    }
}
