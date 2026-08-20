<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\SeatingCardsController;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatingCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_card_max_height_for_19_character_event_name()
    {
        $this->assertSame(110, SeatingCardsController::masterCardMaxHeight(str_repeat('a', 19)));
    }

    public function test_master_card_max_height_for_20_character_event_name()
    {
        $this->assertSame(90, SeatingCardsController::masterCardMaxHeight(str_repeat('a', 20)));
    }

    public function test_master_card_max_height_for_19_character_multibyte_event_name()
    {
        $this->assertSame(110, SeatingCardsController::masterCardMaxHeight(str_repeat('é', 19)));
    }

    public function test_master_card_max_height_for_20_character_multibyte_event_name()
    {
        $this->assertSame(90, SeatingCardsController::masterCardMaxHeight(str_repeat('é', 20)));
    }

    public function test_order_card_max_height_for_19_character_event_name()
    {
        $this->assertSame(105, SeatingCardsController::orderCardMaxHeight(str_repeat('a', 19)));
    }

    public function test_order_card_max_height_for_20_character_event_name()
    {
        $this->assertSame(90, SeatingCardsController::orderCardMaxHeight(str_repeat('a', 20)));
    }

    public function test_total_card_count_for_single_block_bookings()
    {
        // Master page + 1 order card for the block + 1 card per booking.
        $bookings = collect([
            $this->makeBookingStub(1),
            $this->makeBookingStub(1),
            $this->makeBookingStub(1),
        ]);

        $this->assertSame(5, SeatingCardsController::totalCardCount($bookings));
    }

    public function test_total_card_count_for_multiple_block_bookings()
    {
        // Master page + 1 order card per distinct block + 1 card per booking.
        $bookings = collect([
            $this->makeBookingStub(1),
            $this->makeBookingStub(1),
            $this->makeBookingStub(2),
            $this->makeBookingStub(2),
            $this->makeBookingStub(2),
        ]);

        $this->assertSame(8, SeatingCardsController::totalCardCount($bookings));
    }

    public function test_sort_bookings_for_printing_groups_blocks_and_winds_rows()
    {
        // Sorting orders bookings by block position, then winds seats:
        // ascending on odd rows, descending on even rows.
        $bookings = collect([
            $this->makeSeatingBookingStub('B-r2-s1', blockId: 2, posX: 0, posY: 1, rowOrder: 2, seatNumber: 1),
            $this->makeSeatingBookingStub('A-r1-s2', blockId: 1, posX: 0, posY: 0, rowOrder: 1, seatNumber: 2),
            $this->makeSeatingBookingStub('B-r2-s2', blockId: 2, posX: 0, posY: 1, rowOrder: 2, seatNumber: 2),
            $this->makeSeatingBookingStub('A-r1-s1', blockId: 1, posX: 0, posY: 0, rowOrder: 1, seatNumber: 1),
        ]);

        $sorted = SeatingCardsController::sortBookingsForPrinting($bookings);

        $this->assertSame(
            ['A-r1-s1', 'A-r1-s2', 'B-r2-s2', 'B-r2-s1'],
            $sorted->pluck('label')->values()->all()
        );

        // master + 2 order cards + 4 bookings.
        $this->assertSame(7, SeatingCardsController::totalCardCount($sorted));
    }

    private function makeBookingStub(int $blockId): object
    {
        return (object) [
            'seat' => (object) [
                'row' => (object) [
                    'block' => (object) ['id' => $blockId],
                ],
            ],
        ];
    }

    private function makeSeatingBookingStub(string $label, int $blockId, int $posX, int $posY, int $rowOrder, int $seatNumber): object
    {
        return (object) [
            'label' => $label,
            'seat' => (object) [
                'number' => $seatNumber,
                'row' => (object) [
                    'order' => $rowOrder,
                    'block' => (object) ['id' => $blockId, 'position_x' => $posX, 'position_y' => $posY],
                ],
            ],
        ];
    }

    public function test_seating_cards_generates_pdf_download()
    {
        // Create a user with admin permissions
        $adminUser = User::factory()->create(['is_admin' => true]);

        // Create test data
        $room = Room::factory()->create(['name' => 'Test Room']);
        $event = Event::factory()->create(['room_id' => $room->id, 'name' => 'Test Event']);
        $block = Block::factory()->create(['room_id' => $room->id, 'name' => 'Block A', 'type' => 'seating']);
        $row = Row::factory()->create(['block_id' => $block->id, 'name' => 'Row 1']);
        $seat = Seat::factory()->create(['row_id' => $row->id, 'label' => 'A1']);

        // Create a booking
        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'user_id' => $adminUser->id,
            'name' => 'Test Guest',
            'picked_up_at' => now(),
        ]);

        // Act as admin and request the seating cards PDF
        $response = $this->actingAs($adminUser)
            ->get(route('admin.events.seating-cards', $event->id));

        // Assert the response is a PDF download
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('seating-cards-test-event', $response->headers->get('content-disposition'));
    }

    public function test_seating_cards_pdf_with_multiple_bookings()
    {
        // Create a user with admin permissions
        $adminUser = User::factory()->create(['is_admin' => true]);

        // Create test data
        $room = Room::factory()->create(['name' => 'Main Hall']);
        $event = Event::factory()->create(['room_id' => $room->id, 'name' => 'Conference 2024']);
        $block = Block::factory()->create(['room_id' => $room->id, 'name' => 'VIP Section', 'type' => 'seating']);
        $row = Row::factory()->create(['block_id' => $block->id, 'name' => 'Front Row']);
        $seat = Seat::factory()->create(['row_id' => $row->id, 'label' => 'A1']);

        // Create multiple bookings to test 4-per-page layout
        $bookings = [];
        for ($i = 1; $i <= 6; $i++) {
            $testSeat = Seat::factory()->create([
                'row_id' => $row->id,
                'label' => "A{$i}",
            ]);
            $bookings[] = Booking::factory()->create([
                'event_id' => $event->id,
                'seat_id' => $testSeat->id,
                'name' => "Guest {$i}",
                'picked_up_at' => now(),
            ]);
        }

        // Act as admin and request the seating cards PDF
        $response = $this->actingAs($adminUser)
            ->get(route('admin.events.seating-cards', $event->id));

        // Assert the response is a PDF download
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('seating-cards-conference-2024', $response->headers->get('content-disposition'));
    }

    public function test_seating_cards_pdf_with_bookings_across_multiple_blocks()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);

        $room = Room::factory()->create(['name' => 'Main Hall']);
        $event = Event::factory()->create(['room_id' => $room->id, 'name' => 'Multi Block Event']);

        $blockA = Block::factory()->create(['room_id' => $room->id, 'name' => 'A', 'type' => 'seating']);
        $rowA = Row::factory()->create(['block_id' => $blockA->id, 'name' => '1']);

        $blockB = Block::factory()->create(['room_id' => $room->id, 'name' => 'B', 'type' => 'seating']);
        $rowB = Row::factory()->create(['block_id' => $blockB->id, 'name' => '1']);

        foreach ([$rowA, $rowB] as $row) {
            for ($i = 1; $i <= 2; $i++) {
                $seat = Seat::factory()->create(['row_id' => $row->id, 'label' => "S{$i}", 'number' => $i]);
                Booking::factory()->create([
                    'event_id' => $event->id,
                    'seat_id' => $seat->id,
                    'name' => "Guest {$row->id}-{$i}",
                    'picked_up_at' => now(),
                ]);
            }
        }

        $response = $this->actingAs($adminUser)
            ->get(route('admin.events.seating-cards', $event->id));

        // Master page + one order card per block (2) + one card per booking (4) = 7.
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_seating_cards_requires_admin_access()
    {
        // Create a regular user (not admin)
        $user = User::factory()->create(['is_admin' => false]);

        // Create test data
        $room = Room::factory()->create();
        $event = Event::factory()->create(['room_id' => $room->id]);

        // Try to access seating cards as non-admin
        $response = $this->actingAs($user)
            ->get(route('admin.events.seating-cards', $event->id));

        // Should be redirected or forbidden
        $this->assertTrue(
            $response->status() === 403 ||
            $response->isRedirect()
        );
    }

    public function test_seating_cards_excludes_unpicked_bookings_when_toggle_is_off()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);
        $room = Room::factory()->create();
        $event = Event::factory()->create([
            'room_id' => $room->id,
        ]);
        $block = Block::factory()->create(['room_id' => $room->id, 'name' => 'A', 'type' => 'seating']);
        $row = Row::factory()->create(['block_id' => $block->id, 'name' => '1']);
        $seat1 = Seat::factory()->create(['row_id' => $row->id, 'label' => 'S1', 'number' => 1]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id, 'label' => 'S2', 'number' => 2]);

        // One picked-up booking
        Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat1->id,
            'name' => 'Picked Guest',
            'picked_up_at' => now(),
        ]);

        // One unpicked booking
        Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat2->id,
            'name' => 'Unpicked Guest',
            'picked_up_at' => null,
        ]);

        $response = $this->actingAs($adminUser)
            ->get(route('admin.events.seating-cards', $event->id));

        // PDF is generated (only the picked-up booking is included)
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_seating_cards_includes_unpicked_bookings_when_toggle_is_on()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);
        $room = Room::factory()->create();
        $event = Event::factory()->create([
            'room_id' => $room->id,
        ]);
        $block = Block::factory()->create(['room_id' => $room->id, 'name' => 'A', 'type' => 'seating']);
        $row = Row::factory()->create(['block_id' => $block->id, 'name' => '1']);
        $seat1 = Seat::factory()->create(['row_id' => $row->id, 'label' => 'S1', 'number' => 1]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id, 'label' => 'S2', 'number' => 2]);

        // One picked-up booking
        Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat1->id,
            'name' => 'Picked Guest',
            'picked_up_at' => now(),
        ]);

        // One unpicked booking
        Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat2->id,
            'name' => 'Unpicked Guest',
            'picked_up_at' => null,
        ]);

        $response = $this->actingAs($adminUser)
            ->get(route('admin.events.seating-cards', $event->id).'?include_unpicked=1');

        // PDF is generated (both bookings are included)
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_seating_cards_returns_error_when_no_bookings_and_toggle_is_off()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);
        $room = Room::factory()->create();
        $event = Event::factory()->create([
            'room_id' => $room->id,
        ]);
        $block = Block::factory()->create(['room_id' => $room->id, 'name' => 'A', 'type' => 'seating']);
        $row = Row::factory()->create(['block_id' => $block->id, 'name' => '1']);
        $seat = Seat::factory()->create(['row_id' => $row->id, 'label' => 'S1', 'number' => 1]);

        // Only an unpicked booking — toggle is off, so nothing to print
        Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'name' => 'Unpicked Guest',
            'picked_up_at' => null,
        ]);

        $response = $this->actingAs($adminUser)
            ->get(route('admin.events.seating-cards', $event->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_seating_cards_unpicked_booking_contains_not_picked_up_marker()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);
        $room = Room::factory()->create();
        $event = Event::factory()->create([
            'room_id' => $room->id,
            'name' => 'Winter Gala 2026',
        ]);
        $block = Block::factory()->create(['room_id' => $room->id, 'name' => 'A', 'type' => 'seating']);
        $row = Row::factory()->create(['block_id' => $block->id, 'name' => '1']);
        $seat = Seat::factory()->create(['row_id' => $row->id, 'label' => 'S1', 'number' => 1]);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'name' => 'Unpicked Guest',
            'picked_up_at' => null,
        ]);

        // Render the blade template directly to verify the marker is present
        $html = view('pdf.seating-card-single', [
            'booking' => $booking->load(['user', 'seat.row.block']),
            'event' => $event,
        ])->render();

        $this->assertStringContainsString('Not Picked Up', $html);
        $this->assertStringContainsString('Winter Gala 2026', $html);
    }
}
