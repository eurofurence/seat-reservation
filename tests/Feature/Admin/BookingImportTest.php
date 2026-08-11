<?php

namespace Tests\Feature\Admin;

use App\Booking\ImportSessionStore;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BookingImportTest extends TestCase
{
    use RefreshDatabase;

    private function createRoomWithSeats(): array
    {
        $room = Room::create(['name' => 'Test Room']);
        $block = Block::create([
            'room_id' => $room->id,
            'name' => 'A',
            'type' => 'seating',
            'order' => 1,
        ]);

        $rowA = Row::create(['block_id' => $block->id, 'name' => '1', 'order' => 1]);
        $rowB = Row::create(['block_id' => $block->id, 'name' => '2', 'order' => 2]);

        $seatA1 = Seat::create(['row_id' => $rowA->id, 'label' => '1', 'number' => 1]);
        $seatA2 = Seat::create(['row_id' => $rowA->id, 'label' => '2', 'number' => 2]);
        $seatB1 = Seat::create(['row_id' => $rowB->id, 'label' => '1', 'number' => 1]);
        $seatB2 = Seat::create(['row_id' => $rowB->id, 'label' => '2', 'number' => 2]);

        return [$room, $block, $rowA, $rowB, $seatA1, $seatA2, $seatB1, $seatB2];
    }

    /**
     * A room with 3 blocks (left, center, right) in that room order, so "center" is NOT
     * first - used to test block-preference/default behavior independent of room order.
     */
    private function createRoomWithMultipleBlocks(): array
    {
        $room = Room::create(['name' => 'Multi Block Room']);

        $left = Block::create(['room_id' => $room->id, 'name' => 'left', 'type' => 'seating', 'order' => 1]);
        $center = Block::create(['room_id' => $room->id, 'name' => 'center', 'type' => 'seating', 'order' => 2]);
        $right = Block::create(['room_id' => $room->id, 'name' => 'right', 'type' => 'seating', 'order' => 3]);

        $leftRow = Row::create(['block_id' => $left->id, 'name' => '1', 'order' => 1]);
        $centerRow = Row::create(['block_id' => $center->id, 'name' => '1', 'order' => 1]);
        $rightRow = Row::create(['block_id' => $right->id, 'name' => '1', 'order' => 1]);

        $leftSeats = collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $leftRow->id, 'label' => $label, 'number' => $i + 1]));
        $centerSeats = collect(['1', '2', '3'])->map(fn ($label, $i) => Seat::create(['row_id' => $centerRow->id, 'label' => $label, 'number' => $i + 1]));
        $rightSeats = collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $rightRow->id, 'label' => $label, 'number' => $i + 1]));

        return [$room, $leftSeats, $centerSeats, $rightSeats];
    }

    private function createEvent(Room $room, int $maxTickets = 50): Event
    {
        return Event::create([
            'name' => 'Test Event',
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => $maxTickets,
        ]);
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function previewProps($eventId): array
    {
        $response = $this->get(route('admin.events.import-bookings.preview', $eventId));

        return $response->getOriginalContent()->getData()['page']['props'];
    }

    /** @test */
    public function exact_seat_rows_are_booked_after_confirm()
    {
        $admin = $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "John Doe,VIP guest,A,1,1\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertEquals([$seatA1->id], $props['proposal'][0]['seat_ids']);

        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'John Doe', 'comment' => 'VIP guest', 'seat_ids' => [$seatA1->id]],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.show', $event->id));
        $this->assertDatabaseHas('bookings', [
            'event_id' => $event->id,
            'seat_id' => $seatA1->id,
            'name' => 'John Doe',
            'comment' => 'VIP guest',
            'type' => 'admin',
            'user_id' => null,
            'booking_code' => null,
            'created_by_name' => $admin->name,
        ]);
    }

    /** @test */
    public function user_display_name_column_is_surfaced_on_the_proposal()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Row 1 is an exact seat; rows 2/3 are the same auto-assign guest (group path).
        // The last row omits the column entirely to prove missing values stay null.
        $csv = "Guest Name,Comment,Block,Row,Seat,User display name\n".
            "John Doe,VIP guest,A,1,1,Jane Organizer\n".
            "Group Guest,,,,,Carla Coordinator\n".
            "Group Guest,,,,,Carla Coordinator\n".
            "No Name Guest,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $byName = collect($this->previewProps($event->id)['proposal'])->keyBy('guest_name');

        $this->assertSame('Jane Organizer', $byName['John Doe']['user_display_name']);
        $this->assertSame('Carla Coordinator', $byName['Group Guest']['user_display_name']);
        $this->assertNull($byName['No Name Guest']['user_display_name']);
    }

    /** @test */
    public function edited_guest_name_is_booked_and_still_passes_the_quota_guard()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "John Doe,VIP guest,A,1,1\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        // Admin renames the guest on the review screen; original_guest_name pins the quota
        // alignment guard to the untouched name the proposal was built with.
        $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Roe', 'original_guest_name' => 'John Doe', 'comment' => 'VIP guest', 'seat_ids' => [$seatA1->id]],
            ],
        ])->assertRedirect(route('admin.events.show', $event->id));

        $this->assertDatabaseHas('bookings', [
            'event_id' => $event->id,
            'seat_id' => $seatA1->id,
            'name' => 'Jane Roe',
            'type' => 'admin',
        ]);
    }

    /** @test */
    public function imported_bookings_are_not_marked_picked_up_automatically()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "John Doe,VIP guest,A,1,1\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'John Doe', 'comment' => 'VIP guest', 'seat_ids' => [$seatA1->id]],
            ],
        ])->assertRedirect(route('admin.events.show', $event->id));

        $booking = Booking::where('event_id', $event->id)->where('seat_id', $seatA1->id)->first();
        $this->assertNull($booking->picked_up_at);
    }

    /** @test */
    public function blank_seat_rows_fill_a_whole_deeper_row_instead_of_spanning_a_row_boundary_when_it_fits()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, , $seatB1, $seatB2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Book seat A1, leaving A2 (row 1) and B1+B2 (row 2) free. Requesting 2 seats should
        // land entirely in row 2 (a whole row) instead of spilling A2 + B1 across the row
        // A/row B boundary.
        Booking::create([
            'event_id' => $event->id,
            'seat_id' => $seatA1->id,
            'user_id' => null,
            'name' => 'Existing Guest',
            'type' => 'admin',
        ]);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Jane Smith,,,,\n".
            "Jane Smith,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertEqualsCanonicalizing([$seatB1->id, $seatB2->id], $props['proposal'][0]['seat_ids']);
    }

    /** @test */
    public function blank_seat_rows_span_a_row_boundary_only_when_no_single_row_fits_the_quantity()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2, $seatB1, $seatB2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Book seat A1, leaving only 3 free seats total (A2, B1, B2). Neither row alone has
        // 3 free seats, so a request for 3 must span the row A/row B boundary.
        Booking::create([
            'event_id' => $event->id,
            'seat_id' => $seatA1->id,
            'user_id' => null,
            'name' => 'Existing Guest',
            'type' => 'admin',
        ]);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Jane Smith,,,,,3\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertEqualsCanonicalizing([$seatA2->id, $seatB1->id, $seatB2->id], $props['proposal'][0]['seat_ids']);
    }

    /** @test */
    public function blank_seat_rows_distribute_row_by_row_across_blocks_instead_of_draining_one_block_first()
    {
        $this->actingAsAdmin();

        // Two blocks ("left" first in room order, "center" preferred by default), each with
        // 3 rows of 2 seats.
        $room = Room::create(['name' => 'Multi Row Room']);
        $left = Block::create(['room_id' => $room->id, 'name' => 'left', 'type' => 'seating', 'order' => 1]);
        $center = Block::create(['room_id' => $room->id, 'name' => 'center', 'type' => 'seating', 'order' => 2]);

        $leftRows = collect([1, 2, 3])->map(fn ($n) => Row::create(['block_id' => $left->id, 'name' => (string) $n, 'order' => $n]));
        $centerRows = collect([1, 2, 3])->map(fn ($n) => Row::create(['block_id' => $center->id, 'name' => (string) $n, 'order' => $n]));

        $leftSeats = $leftRows->map(fn ($row) => collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $row->id, 'label' => $label, 'number' => $i + 1])));
        $centerSeats = $centerRows->map(fn ($row) => collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $row->id, 'label' => $label, 'number' => $i + 1])));

        $event = $this->createEvent($room);

        // Fill both blocks' front row (row 1) already, so 4 guests of 2 seats each must be
        // placed across rows 2 and 3.
        foreach ([$leftSeats[0], $centerSeats[0]] as $rowSeats) {
            foreach ($rowSeats as $seat) {
                Booking::create(['event_id' => $event->id, 'seat_id' => $seat->id, 'user_id' => null, 'name' => 'Prebooked', 'type' => 'admin']);
            }
        }

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Guest One,,,,,2\n".
            "Guest Two,,,,,2\n".
            "Guest Three,,,,,2\n".
            "Guest Four,,,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $byName = collect($this->previewProps($event->id)['proposal'])->keyBy('guest_name');

        // Row 2 first, in both blocks ("center" preferred), before either block's row 3.
        $this->assertEqualsCanonicalizing($centerSeats[1]->pluck('id')->all(), $byName['Guest One']['seat_ids']);
        $this->assertEqualsCanonicalizing($leftSeats[1]->pluck('id')->all(), $byName['Guest Two']['seat_ids']);
        $this->assertEqualsCanonicalizing($centerSeats[2]->pluck('id')->all(), $byName['Guest Three']['seat_ids']);
        $this->assertEqualsCanonicalizing($leftSeats[2]->pluck('id')->all(), $byName['Guest Four']['seat_ids']);
    }

    /** @test */
    public function auto_assign_defaults_to_center_block_when_none_specified()
    {
        $this->actingAsAdmin();
        [$room, $leftSeats, $centerSeats] = $this->createRoomWithMultipleBlocks();
        $event = $this->createEvent($room);

        // "left" is first in room order, but with no Block specified the preference should
        // default to "center" and land there instead, even though it's not first.
        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "No Preference,,,,\n".
            "No Preference,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $assignedIds = $props['proposal'][0]['seat_ids'];
        $this->assertCount(2, $assignedIds);
        $this->assertEqualsCanonicalizing($assignedIds, array_intersect($assignedIds, $centerSeats->pluck('id')->all()));
        $this->assertEmpty(array_intersect($assignedIds, $leftSeats->pluck('id')->all()));
    }

    /** @test */
    public function auto_assign_center_default_fuzzy_matches_a_block_named_front_center()
    {
        $this->actingAsAdmin();
        $room = Room::create(['name' => 'Fuzzy Block Room']);

        // No block is named exactly "center" - only "Front Center"/"Back Center" - so the
        // default "center" preference must fall back to a contains-match.
        $left = Block::create(['room_id' => $room->id, 'name' => 'Left', 'type' => 'seating', 'order' => 1]);
        $frontCenter = Block::create(['room_id' => $room->id, 'name' => 'Front Center', 'type' => 'seating', 'order' => 2]);
        $backCenter = Block::create(['room_id' => $room->id, 'name' => 'Back Center', 'type' => 'seating', 'order' => 3]);

        $leftRow = Row::create(['block_id' => $left->id, 'name' => '1', 'order' => 1]);
        $frontRow = Row::create(['block_id' => $frontCenter->id, 'name' => '1', 'order' => 1]);
        Row::create(['block_id' => $backCenter->id, 'name' => '1', 'order' => 1]);

        $leftSeats = collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $leftRow->id, 'label' => $label, 'number' => $i + 1]));
        $frontSeats = collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $frontRow->id, 'label' => $label, 'number' => $i + 1]));

        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "No Preference,,,,\n".
            "No Preference,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $assignedIds = $props['proposal'][0]['seat_ids'];
        $this->assertCount(2, $assignedIds);
        // "Front Center" is the first block whose name contains "center".
        $this->assertEqualsCanonicalizing($assignedIds, array_intersect($assignedIds, $frontSeats->pluck('id')->all()));
        $this->assertEmpty(array_intersect($assignedIds, $leftSeats->pluck('id')->all()));
    }

    /** @test */
    public function auto_assign_overflows_to_other_blocks_front_row_before_center_back_rows()
    {
        $this->actingAsAdmin();

        // Room where the center block has TWO rows, plus a left block with a front row.
        $room = Room::create(['name' => 'Front Row Room']);
        $left = Block::create(['room_id' => $room->id, 'name' => 'left', 'type' => 'seating', 'order' => 1]);
        $center = Block::create(['room_id' => $room->id, 'name' => 'center', 'type' => 'seating', 'order' => 2]);

        $leftRow1 = Row::create(['block_id' => $left->id, 'name' => '1', 'order' => 1]);
        $centerRow1 = Row::create(['block_id' => $center->id, 'name' => '1', 'order' => 1]);
        $centerRow2 = Row::create(['block_id' => $center->id, 'name' => '2', 'order' => 2]);

        $leftRow1Seats = collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $leftRow1->id, 'label' => $label, 'number' => $i + 1]));
        $centerRow1Seats = collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $centerRow1->id, 'label' => $label, 'number' => $i + 1]));
        $centerRow2Seats = collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $centerRow2->id, 'label' => $label, 'number' => $i + 1]));

        $event = $this->createEvent($room);

        // Fill the center FRONT row so a no-preference group can't fit there.
        foreach ($centerRow1Seats as $seat) {
            Booking::create([
                'event_id' => $event->id,
                'seat_id' => $seat->id,
                'user_id' => null,
                'name' => 'Prebooked',
                'type' => 'admin',
            ]);
        }

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Overflow,,,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $assignedIds = $props['proposal'][0]['seat_ids'];

        // The group must land in the LEFT block's front row, NOT sink into center's back row.
        $this->assertCount(2, $assignedIds);
        $this->assertEqualsCanonicalizing($leftRow1Seats->pluck('id')->all(), $assignedIds);
        $this->assertEmpty(array_intersect($assignedIds, $centerRow2Seats->pluck('id')->all()));
    }

    /** @test */
    public function auto_assign_uses_an_explicit_preferred_block()
    {
        $this->actingAsAdmin();
        [$room, , , $rightSeats] = $this->createRoomWithMultipleBlocks();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Right Fan,,right,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $assignedIds = $props['proposal'][0]['seat_ids'];
        $this->assertCount(1, $assignedIds);
        $this->assertContains($assignedIds[0], $rightSeats->pluck('id')->all());
    }

    /** @test */
    public function auto_assign_leaves_guest_unplaced_when_preferred_block_does_not_exist()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithMultipleBlocks();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Typo Fan,,fromt,,\n"; // no such block

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $guest = $props['proposal'][0];
        // Left unplaced for manual review rather than silently seated elsewhere in the room -
        // the admin can still place them via "Auto-place seats" on the review screen.
        $this->assertSame([], $guest['seat_ids']);
        $this->assertTrue($guest['unresolved']);
        $this->assertStringContainsString("Block 'fromt' was not found", $guest['unresolved_reason']);
    }

    /** @test */
    public function auto_assign_leaves_guest_unresolved_when_preferred_block_lacks_capacity()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithMultipleBlocks(); // "left" only has 2 seats
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Big Party,,left,,\n".
            "Big Party,,left,,\n".
            "Big Party,,left,,\n"; // needs 3, left only fits 2

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $guest = $props['proposal'][0];
        // "left" can't fit 3 - left unresolved for manual placement rather than silently
        // spilling into another block, even though the room has free seats elsewhere.
        $this->assertSame([], $guest['seat_ids']);
        $this->assertTrue($guest['unresolved']);
        $this->assertStringContainsString("Block 'left' doesn't have 3 contiguous free seat(s)", $guest['unresolved_reason']);
    }

    /** @test */
    public function mixed_exact_and_auto_rows_do_not_overlap()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2, $seatB1, $seatB2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Exact Guest,,A,1,1\n".
            "Auto Guest,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(2, $props['proposal']);
        $this->assertEquals([$seatA1->id], $props['proposal'][0]['seat_ids']);
        // Auto guest gets the next available seat (A2), not the exact-claimed A1.
        $this->assertEquals([$seatA2->id], $props['proposal'][1]['seat_ids']);
    }

    /** @test */
    public function ambiguous_partial_seat_columns_auto_assign_instead_of_aborting()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithMultipleBlocks(); // has a "center" block for Row Only to resolve against
        $event = $this->createEvent($room);

        // Block alone is a valid "preferred block" shape (see auto_assign_uses_an_explicit_
        // preferred_block). Row+Seat without a Block (and only-Row/only-Seat) don't uniquely
        // identify a seat either, but rather than needing a manual pick, the Row/Seat hint is
        // discarded and each guest is auto-assigned a seat (defaulting to "center" preference,
        // same as a fully-blank row) - no guest is left unresolved.
        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Row And Seat Only,,,1,1\n".
            "Row Only,,,1,\n".
            "Seat Only,,,,1\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(3, $props['proposal']);

        $allAssignedIds = [];
        foreach ($props['proposal'] as $guest) {
            $this->assertCount(1, $guest['seat_ids']);
            $this->assertArrayNotHasKey('unresolved', $guest);
            $allAssignedIds[] = $guest['seat_ids'][0];
        }

        // Each guest got a distinct seat.
        $this->assertCount(3, array_unique($allAssignedIds));

        // Confirm succeeds immediately - nothing needed manual resolution.
        $confirmGuests = collect($props['proposal'])->map(fn ($g) => [
            'guest_name' => $g['guest_name'],
            'comment' => $g['comment'],
            'seat_ids' => $g['seat_ids'],
        ])->all();

        $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => $confirmGuests,
        ])->assertRedirect(route('admin.events.show', $event->id));

        $this->assertEquals(3, Booking::where('event_id', $event->id)->count());
    }

    /** @test */
    public function unmatched_exact_seat_reaches_preview_as_unresolved_instead_of_aborting()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Frank Ghost,,A,1,99\n"; // no such seat label

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertSame([], $props['proposal'][0]['seat_ids']);
        $this->assertTrue($props['proposal'][0]['unresolved']);

        // Confirming without picking a seat (and without marking them skipped) is rejected
        // server-side too.
        $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Frank Ghost', 'comment' => null, 'seat_ids' => []],
            ],
        ])->assertSessionHas('error', fn ($message) => str_contains($message, 'Frank Ghost needs at least 1 seat'));
        $this->assertDatabaseCount('bookings', 0);

        // Once the admin manually picks a real seat in the preview, confirm succeeds.
        $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Frank Ghost', 'comment' => null, 'seat_ids' => [$seatA1->id]],
            ],
        ])->assertRedirect(route('admin.events.show', $event->id));

        $this->assertDatabaseHas('bookings', [
            'event_id' => $event->id,
            'seat_id' => $seatA1->id,
            'name' => 'Frank Ghost',
        ]);
    }

    /** @test */
    public function import_exceeding_room_capacity_is_rejected_entirely()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats(); // only 4 seats total
        $event = $this->createEvent($room);

        // 10 rows for the same guest (no preference given) collapse into one group asking
        // for 10 seats - more than this 4-seat room has anywhere, even non-contiguously, so
        // there's truly nothing to place them "not-placed" for - the whole import aborts.
        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            implode('', array_fill(0, 10, "Big Group,,,,\n"));

        $response = $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function import_is_not_limited_by_the_events_ticket_cap()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats(); // 4 physical seats
        $event = $this->createEvent($room, maxTickets: 1); // cap of 1, but importing 2

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Guest One,,,,\n".
            "Guest One,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertCount(2, $props['proposal'][0]['seat_ids']);
        $this->assertEquals(2, $props['proposal'][0]['requested_seats']);
    }

    /** @test */
    public function confirm_rejects_booking_fewer_seats_than_requested()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Jane Smith,,,,\n".
            "Jane Smith,,,,\n".
            "Jane Smith,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertEquals(3, $props['proposal'][0]['requested_seats']);
        $proposedSeatIds = $props['proposal'][0]['seat_ids'];
        $this->assertCount(3, $proposedSeatIds);

        // Admin removes a seat on the map, submitting only 2 of the 3 requested seats.
        $response = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Smith', 'comment' => null, 'seat_ids' => array_slice($proposedSeatIds, 0, 2)],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);

        // Submitting the full requested count succeeds.
        $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Smith', 'comment' => null, 'seat_ids' => $proposedSeatIds],
            ],
        ])->assertRedirect(route('admin.events.show', $event->id));

        $this->assertEquals(3, Booking::where('event_id', $event->id)->where('name', 'Jane Smith')->count());
    }

    /** @test */
    public function confirm_rejects_the_same_seat_assigned_more_than_once()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Build a valid pending proposal for two guests first - the confirm endpoint
        // rejects payloads that have no matching session proposal before it ever reaches
        // the duplicate-seat guard.
        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Guest A,,,,\n".
            "Guest B,,,,\n";
        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $response = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Guest A', 'comment' => null, 'seat_ids' => [$seatA1->id]],
                ['guest_name' => 'Guest B', 'comment' => null, 'seat_ids' => [$seatA1->id]],
            ],
        ]);

        $response->assertSessionHas('error', 'The same seat was assigned more than once. Please review the seat picks and try again.');
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function confirm_rejects_seats_that_belong_to_another_room()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // A seat from a completely separate room must never be bookable against this event.
        [, , , , $otherRoomSeat] = $this->createRoomWithSeats();

        // A valid pending proposal must exist before the room-ownership guard is reached.
        $csv = "Guest Name,Comment,Block,Row,Seat\nGuest A,,,,\n";
        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $response = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Guest A', 'comment' => null, 'seat_ids' => [$otherRoomSeat->id]],
            ],
        ]);

        $response->assertSessionHas('error', "One or more selected seats don't belong to this event's room.");
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function confirm_rejects_a_payload_that_no_longer_matches_the_pending_preview()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\nJane Smith,,A,1,1\n";
        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        // Payload carries a different guest than the pending proposal - reject rather than
        // risk mapping the requested-seats quota onto the wrong guest.
        $response = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Someone Else', 'comment' => null, 'seat_ids' => [$seatA1->id]],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function collision_at_confirm_reassigns_only_the_affected_guest()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2, $seatB1, $seatB2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Guest A,,A,1,1\n".
            "Guest B,,A,2,1\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        // Someone else books Guest A's seat (A1) in the meantime.
        Booking::create([
            'event_id' => $event->id,
            'seat_id' => $seatA1->id,
            'user_id' => null,
            'name' => 'Race Winner',
            'type' => 'admin',
        ]);

        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Guest A', 'comment' => null, 'seat_ids' => [$seatA1->id]],
                ['guest_name' => 'Guest B', 'comment' => null, 'seat_ids' => [$seatB1->id]],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.import-bookings.preview', $event->id));
        $confirmResponse->assertSessionHas('warning');

        // Nothing was actually booked from the import yet - only the pre-existing race booking exists.
        $this->assertDatabaseCount('bookings', 1);

        $props = $this->previewProps($event->id);
        $this->assertCount(2, $props['proposal']);

        $guestA = collect($props['proposal'])->firstWhere('guest_name', 'Guest A');
        $guestB = collect($props['proposal'])->firstWhere('guest_name', 'Guest B');

        // Guest A got reassigned away from the now-taken seat A1.
        $this->assertNotEquals([$seatA1->id], $guestA['seat_ids']);
        $this->assertEqualsCanonicalizing([$seatA2->id], $guestA['seat_ids']);
        // Guest B's original assignment is untouched.
        $this->assertEquals([$seatB1->id], $guestB['seat_ids']);
    }

    /** @test */
    public function duplicate_exact_seat_reference_becomes_unresolved_for_the_second_row()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "First Guest,,A,1,1\n".
            "Second Guest,,A,1,1\n"; // same seat referenced twice

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(2, $props['proposal']);
        $this->assertEquals([$seatA1->id], $props['proposal'][0]['seat_ids']);
        $this->assertSame([], $props['proposal'][1]['seat_ids']);
        $this->assertTrue($props['proposal'][1]['unresolved']);
    }

    /** @test */
    public function already_booked_exact_seat_becomes_unresolved_instead_of_aborting()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        Booking::create([
            'event_id' => $event->id,
            'seat_id' => $seatA1->id,
            'user_id' => null,
            'name' => 'Existing Guest',
            'type' => 'admin',
        ]);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "New Guest,,A,1,1\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertSame([], $props['proposal'][0]['seat_ids']);
        $this->assertTrue($props['proposal'][0]['unresolved']);
        $this->assertDatabaseCount('bookings', 1); // only the pre-existing one
    }

    /** @test */
    public function reimporting_a_csv_flags_guests_already_booked_by_name_and_skips_them_on_confirm()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2, $seatB1, $seatB2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Simulates a prior import: Jane Doe got an exact seat, Bob Smith was auto-assigned.
        Booking::create(['event_id' => $event->id, 'seat_id' => $seatA1->id, 'user_id' => null, 'name' => 'Jane Doe', 'type' => 'admin']);
        Booking::create(['event_id' => $event->id, 'seat_id' => $seatB1->id, 'user_id' => null, 'name' => 'Bob Smith', 'type' => 'admin']);

        // Re-imported CSV: same two guests again (one exact-seat row, one blank/auto-assign
        // row - same shape they'd have had originally) plus one brand new guest.
        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Jane Doe,,A,1,1\n".
            "Bob Smith,,,,\n".
            "New Guest,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $byName = collect($props['proposal'])->keyBy('guest_name');

        $this->assertCount(3, $props['proposal']);
        $this->assertTrue($byName['Jane Doe']['already_booked']);
        $this->assertEquals([$seatA1->id], $byName['Jane Doe']['seat_ids']);
        $this->assertTrue($byName['Bob Smith']['already_booked']);
        $this->assertEquals([$seatB1->id], $byName['Bob Smith']['seat_ids']);
        $this->assertArrayNotHasKey('already_booked', $byName['New Guest']);
        $this->assertEquals([$seatA2->id], $byName['New Guest']['seat_ids']);

        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Doe', 'comment' => null, 'seat_ids' => [$seatA1->id]],
                ['guest_name' => 'Bob Smith', 'comment' => null, 'seat_ids' => [$seatB1->id]],
                ['guest_name' => 'New Guest', 'comment' => null, 'seat_ids' => [$seatA2->id]],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.show', $event->id));
        $confirmResponse->assertSessionHas('success', fn ($message) => str_contains($message, 'Matched 2 already-booked guest(s).'));

        // Only the 2 pre-existing bookings plus the 1 genuinely new one - no duplicates.
        $this->assertDatabaseCount('bookings', 3);
        $this->assertDatabaseHas('bookings', ['event_id' => $event->id, 'seat_id' => $seatA2->id, 'name' => 'New Guest']);
        $this->assertSame(1, Booking::where('name', 'Jane Doe')->count());
    }

    /** @test */
    public function reimport_flags_a_seat_count_mismatch_for_an_already_booked_guest()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        Booking::create(['event_id' => $event->id, 'seat_id' => $seatA1->id, 'user_id' => null, 'name' => 'Jane Doe', 'type' => 'admin']);

        // Re-import now asks for 2 seats for Jane Doe, but she only has 1 booked.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Jane Doe,,,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $jane = $props['proposal'][0];

        $this->assertTrue($jane['already_booked']);
        $this->assertTrue($jane['seat_count_mismatch']);
        $this->assertEquals(2, $jane['csv_requested_seats']);
        $this->assertEquals([$seatA1->id], $jane['seat_ids']);
    }

    /** @test */
    public function reimport_surfaces_the_csv_seat_preference_for_an_already_booked_guest()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        Booking::create(['event_id' => $event->id, 'seat_id' => $seatA1->id, 'user_id' => null, 'name' => 'Jane Doe', 'type' => 'admin']);

        // Re-import now asks for 2 seats for Jane Doe, preferring Block B this time.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Jane Doe,,B,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $jane = $props['proposal'][0];

        $this->assertTrue($jane['already_booked']);
        $this->assertEquals('B', $jane['preferred_block_name']);
        $this->assertNull($jane['preferred_row_name']);
    }

    /** @test */
    public function skipping_a_fully_resolved_new_guest_excludes_them_from_this_confirm()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Both guests are fully resolved exact-seat rows - neither is under quota or
        // already booked, so skipping here is a pure "defer this guest" choice, not
        // resolving a blocker.
        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Skip Me,,A,1,1\n".
            "Book Me,,A,1,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $byName = collect($props['proposal'])->keyBy('guest_name');
        $this->assertEquals([$seatA1->id], $byName['Skip Me']['seat_ids']);
        $this->assertArrayNotHasKey('unresolved', $byName['Skip Me']);

        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Skip Me', 'comment' => null, 'seat_ids' => [$seatA1->id], 'skipped' => true],
                ['guest_name' => 'Book Me', 'comment' => null, 'seat_ids' => [$seatA2->id]],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.show', $event->id));
        $confirmResponse->assertSessionHas('success', fn ($message) => str_contains($message, 'Skipped 1 guest(s)'));

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['event_id' => $event->id, 'seat_id' => $seatA2->id, 'name' => 'Book Me']);
        $this->assertDatabaseMissing('bookings', ['name' => 'Skip Me']);
    }

    /** @test */
    public function skipping_an_unresolved_guest_lets_confirm_proceed_without_booking_them()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Frank Ghost,,A,1,99\n". // seat doesn't exist -> unresolved, 0 seats
            "Real Guest,,A,1,1\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $byName = collect($props['proposal'])->keyBy('guest_name');
        $this->assertTrue($byName['Frank Ghost']['unresolved']);
        $this->assertSame([], $byName['Frank Ghost']['seat_ids']);

        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Frank Ghost', 'comment' => null, 'seat_ids' => [], 'skipped' => true],
                ['guest_name' => 'Real Guest', 'comment' => null, 'seat_ids' => [$seatA1->id]],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.show', $event->id));
        $confirmResponse->assertSessionHas('success', fn ($message) => str_contains($message, 'Skipped 1 guest(s)'));

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['event_id' => $event->id, 'seat_id' => $seatA1->id, 'name' => 'Real Guest']);
        $this->assertDatabaseMissing('bookings', ['name' => 'Frank Ghost']);
    }

    /** @test */
    public function skipping_an_already_booked_guest_leaves_their_existing_booking_untouched()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        Booking::create(['event_id' => $event->id, 'seat_id' => $seatA1->id, 'user_id' => null, 'name' => 'Jane Doe', 'comment' => 'old note', 'type' => 'admin']);

        // Re-import now asks for 2 seats for Jane Doe, but she only has 1 booked - a mismatch
        // that would normally block Confirm until resolved.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Jane Doe,,,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertTrue($props['proposal'][0]['already_booked']);
        $this->assertTrue($props['proposal'][0]['seat_count_mismatch']);

        // Admin skips instead of resolving - edits to name/comment are ignored while skipped.
        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Renamed', 'original_guest_name' => 'Jane Doe', 'comment' => 'new note', 'seat_ids' => [$seatA1->id], 'skipped' => true],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.show', $event->id));
        $confirmResponse->assertSessionHas('success', fn ($message) => str_contains($message, 'Skipped 1 guest(s)'));

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', [
            'event_id' => $event->id,
            'seat_id' => $seatA1->id,
            'name' => 'Jane Doe',
            'comment' => 'old note',
        ]);
    }

    /** @test */
    public function editing_an_already_booked_guests_name_on_confirm_bulk_renames_their_existing_booking()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $existing = Booking::create(['event_id' => $event->id, 'seat_id' => $seatA1->id, 'user_id' => null, 'name' => 'Jane Doe', 'comment' => 'old note', 'type' => 'admin']);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Jane Doe,,A,1,1\n".
            "New Guest,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        // Admin edits Jane's name/comment on the review screen before confirming - the posted
        // guest_name/comment differ from original_guest_name/original comment.
        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Smith', 'original_guest_name' => 'Jane Doe', 'comment' => 'new note', 'seat_ids' => [$seatA1->id]],
                ['guest_name' => 'New Guest', 'comment' => null, 'seat_ids' => [$seatA2->id]],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.show', $event->id));
        $confirmResponse->assertSessionHas('success', fn ($message) => str_contains($message, 'Renamed 1.'));

        $this->assertDatabaseCount('bookings', 2);
        $existing->refresh();
        $this->assertSame('Jane Smith', $existing->name);
        $this->assertSame('new note', $existing->comment);
    }

    /** @test */
    public function rejected_confirm_leaves_an_already_booked_guests_pending_rename_and_seat_change_uncommitted()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $existing = Booking::create(['event_id' => $event->id, 'seat_id' => $seatA1->id, 'user_id' => null, 'name' => 'Jane Doe', 'comment' => 'old note', 'type' => 'admin']);

        // Jane's csv row now targets seat A2 instead of her existing A1 - a rename plus a
        // seat swap (free A1, book A2) would be queued for her on confirm. A second guest
        // is proposed too, so the confirm payload can carry the same guest count/indexes
        // the session proposal expects (a mismatched payload is rejected before it even
        // reaches the duplicate-seat guard).
        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Jane Doe,,A,1,2\n".
            "Guest B,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        // The confirm payload also assigns Guest B to the SAME seat A2, which the
        // duplicate-seat guard rejects. Jane's queued rename/seat-swap must not be committed
        // either - renames/deletes are only ever applied once every validation gate below
        // them passes (see ConfirmImportController's $pendingRenames/$removedBookingIds).
        $response = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Smith', 'original_guest_name' => 'Jane Doe', 'comment' => 'new note', 'seat_ids' => [$seatA2->id]],
                ['guest_name' => 'Guest B', 'comment' => null, 'seat_ids' => [$seatA2->id]],
            ],
        ]);

        $response->assertSessionHas('error', 'The same seat was assigned more than once. Please review the seat picks and try again.');

        $this->assertDatabaseCount('bookings', 1);
        $existing->refresh();
        $this->assertSame('Jane Doe', $existing->name);
        $this->assertSame('old note', $existing->comment);
        $this->assertSame($seatA1->id, $existing->seat_id);
    }

    /** @test */
    public function confirm_adds_a_new_seat_for_an_already_booked_guest_whose_csv_now_requests_more()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        Booking::create(['event_id' => $event->id, 'seat_id' => $seatA1->id, 'user_id' => null, 'name' => 'Jane Doe', 'type' => 'admin']);

        // Re-import now asks for 2 seats for Jane Doe, but she only has 1 booked.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Jane Doe,,,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        // Admin adds the second seat on the review screen's seat map before confirming.
        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Doe', 'original_guest_name' => 'Jane Doe', 'comment' => null, 'seat_ids' => [$seatA1->id, $seatA2->id]],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.show', $event->id));
        $confirmResponse->assertSessionHas('success', fn ($message) => str_contains($message, 'Added 1 seat(s) for them.'));

        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseHas('bookings', ['event_id' => $event->id, 'seat_id' => $seatA1->id, 'name' => 'Jane Doe']);
        $this->assertDatabaseHas('bookings', ['event_id' => $event->id, 'seat_id' => $seatA2->id, 'name' => 'Jane Doe']);
    }

    /** @test */
    public function confirm_frees_a_seat_for_an_already_booked_guest_whose_csv_now_requests_fewer()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        Booking::create(['event_id' => $event->id, 'seat_id' => $seatA1->id, 'user_id' => null, 'name' => 'Jane Doe', 'type' => 'admin']);
        $keptBooking = Booking::create(['event_id' => $event->id, 'seat_id' => $seatA2->id, 'user_id' => null, 'name' => 'Jane Doe', 'type' => 'admin']);

        // Re-import now only asks for 1 seat for Jane Doe, but she has 2 booked.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Jane Doe,,,,,1\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        // Admin clears the extra seat on the review screen's seat map before confirming.
        $confirmResponse = $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Jane Doe', 'original_guest_name' => 'Jane Doe', 'comment' => null, 'seat_ids' => [$seatA1->id]],
            ],
        ]);

        $confirmResponse->assertRedirect(route('admin.events.show', $event->id));
        $confirmResponse->assertSessionHas('success', fn ($message) => str_contains($message, 'Freed 1 seat(s) for them.'));

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['event_id' => $event->id, 'seat_id' => $seatA1->id, 'name' => 'Jane Doe']);
        $this->assertDatabaseMissing('bookings', ['id' => $keptBooking->id]);
    }

    /** @test */
    public function confirm_allows_more_seats_than_requested()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1, $seatA2] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat\n".
            "Solo Guest,,,,\n"; // requests 1 seat

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertEquals(1, $props['proposal'][0]['requested_seats']);

        // Admin adds an extra seat on the map beyond what was requested - not blocked.
        $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => 'Solo Guest', 'comment' => null, 'seat_ids' => [$seatA1->id, $seatA2->id]],
            ],
        ])->assertRedirect(route('admin.events.show', $event->id));

        $this->assertEquals(2, Booking::where('event_id', $event->id)->where('name', 'Solo Guest')->count());
    }

    /** @test */
    public function malformed_csv_header_is_rejected()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Name,Notes,Section,Row,Seat\n".
            "Someone,,A,1,1\n";

        $response = $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function non_csv_file_upload_is_rejected()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $mp3 = UploadedFile::fake()->create('song.mp3', 10, 'audio/mpeg');

        $response = $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $mp3,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function csv_with_utf8_bom_is_accepted()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Excel/Nextcloud Forms exports prefix the file with a UTF-8 BOM.
        $csv = "\xEF\xBB\xBFGuest Name\n".
            "Bom Guest\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertEquals('Bom Guest', $props['proposal'][0]['guest_name']);
    }

    /** @test */
    public function overlong_guest_name_and_comment_are_capped_and_still_import()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $longName = str_repeat('N', 300);
        $longComment = str_repeat('C', 1200);

        $csv = "Guest Name,Comment\n".
            "{$longName},{$longComment}\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $guest = $props['proposal'][0];
        $this->assertEquals(str_repeat('N', 255), $guest['guest_name']);
        $this->assertEquals(str_repeat('C', 1000), $guest['comment']);

        // The capped values must pass confirm's validation and actually book.
        $this->post(route('admin.events.import-bookings.confirm', $event->id), [
            'guests' => [
                ['guest_name' => $guest['guest_name'], 'comment' => $guest['comment'], 'seat_ids' => $guest['seat_ids']],
            ],
        ])->assertRedirect(route('admin.events.show', $event->id));

        $this->assertDatabaseHas('bookings', ['name' => str_repeat('N', 255)]);
    }

    /** @test */
    public function csv_without_optional_columns_still_imports()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Only the mandatory Guest Name column - as a Nextcloud Forms export without
        // Block/Row/Seat questions would produce.
        $csv = "Guest Name\n".
            "Solo Guest\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertEquals('Solo Guest', $props['proposal'][0]['guest_name']);
        $this->assertCount(1, $props['proposal'][0]['seat_ids']);
    }

    /** @test */
    public function csv_with_extra_unrelated_column_still_imports()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Simulates Nextcloud Forms adding its own extra columns (Timestamp, a Telegram
        // handle question, etc.) - these should just be ignored, not break parsing.
        $csv = "Guest Name,Comment,Block,Row,Seat,Telegram Handle\n".
            "Extra Col Guest,Has extra col,,,,@handle\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertEquals('Extra Col Guest', $props['proposal'][0]['guest_name']);
        $this->assertEquals('Has extra col', $props['proposal'][0]['comment']);
        $this->assertCount(1, $props['proposal'][0]['seat_ids']);
    }

    /** @test */
    public function single_event_import_honors_an_optional_event_column_and_skips_other_events_rows()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // A CSV with an Event column (e.g. exported from a shared form covering several
        // events) uploaded via the single-event flow: only the rows naming this event (or
        // leaving it blank) should be imported - rows naming another event are dropped, not
        // silently booked onto this one.
        $csv = "Guest Name,Event,Block,Row,Seat\n".
            "This Event Guest,Test Event,A,1,1\n".
            "Other Event Guest,Some Other Event,,,\n".
            "No Event Column Guest,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])
            ->assertRedirect(route('admin.events.import-bookings.preview', $event->id))
            ->assertSessionHas('info');

        $props = $this->previewProps($event->id);
        $names = array_column($props['proposal'], 'guest_name');
        $this->assertCount(2, $props['proposal']);
        $this->assertContains('This Event Guest', $names);
        $this->assertContains('No Event Column Guest', $names);
        $this->assertNotContains('Other Event Guest', $names);
    }

    /** @test */
    public function single_event_import_rejects_a_csv_whose_event_column_never_matches()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Event\n".
            "Some Guest,A Totally Different Event\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(app(ImportSessionStore::class)->get("import_proposal:{$event->id}"));
    }

    /** @test */
    public function number_of_seats_column_expands_auto_assign_quantity()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Group Guest,,,,,3\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertCount(3, $props['proposal'][0]['seat_ids']);
        $this->assertEquals(3, $props['proposal'][0]['requested_seats']);
    }

    /** @test */
    public function blank_number_of_seats_defaults_to_one()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Single Guest,,,,,\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal'][0]['seat_ids']);
    }

    /** @test */
    public function non_numeric_number_of_seats_rejects_the_whole_import()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "John Doe,,,,,\n".
            "Bad Guest,,,,,two\n";

        $response = $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($error) => str_contains($error, 'Number of Seats'));
        $this->assertNull(app(ImportSessionStore::class)->get("import_proposal:{$event->id}"));
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function number_of_seats_exceeding_room_capacity_rejects_the_whole_import()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        // Room only has 4 seats (see createRoomWithSeats) - there's no arbitrary cap, the
        // room's real capacity is the only limit. 500 requested seats genuinely can't fit
        // anywhere, even non-contiguously, so the whole import is rejected.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Typo Guest,,,,,500\n".
            "John Doe,,A,1,1,\n";

        $response = $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($error) => str_contains($error, 'Not enough available seats'));
        $this->assertNull(app(ImportSessionStore::class)->get("import_proposal:{$event->id}"));
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function non_admin_cannot_access_import_routes()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $this->get(route('admin.events.import-bookings.template', $event->id))->assertForbidden();
        $this->post(route('admin.events.import-bookings.propose', $event->id), [])->assertForbidden();
        $this->get(route('admin.events.import-bookings.preview', $event->id))->assertForbidden();
        $this->post(route('admin.events.import-bookings.confirm', $event->id), [])->assertForbidden();
    }

    /** @test */
    public function import_template_has_expected_headers()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        $event = $this->createEvent($room);

        $response = $this->get(route('admin.events.import-bookings.template', $event->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("Guest Name,Comment,Block,Row,Seat,Number of Seats,Timestamp\n", $response->getContent());
    }

    /** @test */
    public function global_import_processes_multiple_events_one_at_a_time_then_completes()
    {
        $admin = $this->actingAsAdmin();
        [$room1, , , , $seat1A1] = $this->createRoomWithSeats();
        [$room2, , , , $seat2A1] = $this->createRoomWithSeats();

        $event1 = Event::create([
            'name' => 'Event One',
            'room_id' => $room1->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);
        $event2 = Event::create([
            'name' => 'Event Two',
            'room_id' => $room2->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);

        $csv = "Event,Guest Name,Comment,Block,Row,Seat\n".
            "Event One,Alice,,A,1,1\n".
            "Event Two,Bob,,A,1,1\n";

        $this->post(route('admin.import-bookings.propose'), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event1->id));

        // First event's proposal is ready to review.
        $props = $this->previewProps($event1->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertEquals('Alice', $props['proposal'][0]['guest_name']);

        // Confirming event 1 auto-advances to event 2's proposal instead of returning to
        // event 1's show page.
        $this->post(route('admin.events.import-bookings.confirm', $event1->id), [
            'guests' => [
                ['guest_name' => 'Alice', 'comment' => null, 'seat_ids' => [$seat1A1->id]],
            ],
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event2->id));

        // Global imports stage each event and write nothing until the whole queue is done,
        // so event 1 is NOT booked yet after confirming it.
        $this->assertDatabaseCount('bookings', 0);

        $props = $this->previewProps($event2->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertEquals('Bob', $props['proposal'][0]['guest_name']);

        // Confirming the last event in the queue flushes the whole staged import at once and
        // redirects to the events overview (not the last event's show page).
        $this->post(route('admin.events.import-bookings.confirm', $event2->id), [
            'guests' => [
                ['guest_name' => 'Bob', 'comment' => null, 'seat_ids' => [$seat2A1->id]],
            ],
        ])->assertRedirect(route('admin.events.index'));

        // Only now, at the final flush, are BOTH events' bookings written.
        $this->assertDatabaseHas('bookings', ['event_id' => $event1->id, 'seat_id' => $seat1A1->id, 'name' => 'Alice', 'created_by_name' => $admin->name]);
        $this->assertDatabaseHas('bookings', ['event_id' => $event2->id, 'seat_id' => $seat2A1->id, 'name' => 'Bob', 'created_by_name' => $admin->name]);
        $this->assertDatabaseCount('bookings', 2);
    }

    /** @test */
    public function abandoning_a_global_import_midway_books_nothing()
    {
        $this->actingAsAdmin();
        [$room1, , , , $seat1A1] = $this->createRoomWithSeats();
        [$room2] = $this->createRoomWithSeats();

        $event1 = Event::create([
            'name' => 'Event One',
            'room_id' => $room1->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);
        $event2 = Event::create([
            'name' => 'Event Two',
            'room_id' => $room2->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);

        $csv = "Event,Guest Name,Comment,Block,Row,Seat\n".
            "Event One,Alice,,A,1,1\n".
            "Event Two,Bob,,A,1,1\n";

        $this->post(route('admin.import-bookings.propose'), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event1->id));

        // Confirm only the first event, then walk away (never confirm event 2).
        $this->post(route('admin.events.import-bookings.confirm', $event1->id), [
            'guests' => [
                ['guest_name' => 'Alice', 'comment' => null, 'seat_ids' => [$seat1A1->id]],
            ],
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event2->id));

        // Nothing is booked because the queue never reached its atomic flush.
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function global_import_rejects_unmatched_event_name_upfront()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        Event::create([
            'name' => 'Real Event',
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);

        $csv = "Event,Guest Name,Comment,Block,Row,Seat\n".
            "Nonexistent Event,Alice,,,,\n";

        $response = $this->post(route('admin.import-bookings.propose'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function global_import_rejects_bad_number_of_seats_in_a_later_event_upfront()
    {
        $this->actingAsAdmin();
        [$room1, , , , $seat1A1] = $this->createRoomWithSeats();
        [$room2] = $this->createRoomWithSeats();

        $eventOne = Event::create([
            'name' => 'Event One',
            'room_id' => $room1->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);
        Event::create([
            'name' => 'Event Two',
            'room_id' => $room2->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);

        // Event One's row is completely fine - the bad "Number of Seats" value is on Event
        // Two, further down the file. The whole upload must be rejected upfront, before the
        // admin ever sees Event One's proposal (same as an unmatched Event name would).
        $csv = "Event,Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Event One,Alice,,A,1,1,\n".
            "Event Two,Bob,,,,,two\n";

        $response = $this->post(route('admin.import-bookings.propose'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertNull(app(ImportSessionStore::class)->get('global_import_queue'));
        // Event One's own row was fine, but the later bad row aborts the whole upload upfront -
        // the admin must never see Event One's proposal staged.
        $this->assertNull(app(ImportSessionStore::class)->get("import_proposal:{$eventOne->id}"));
    }

    /** @test */
    public function global_import_matches_event_names_ignoring_case_whitespace_and_symbols()
    {
        $this->actingAsAdmin();
        [$room, , , , $seatA1] = $this->createRoomWithSeats();
        $event = Event::create([
            'name' => 'Opening Ceremony: Hall 3',
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);

        $csv = "Event,Guest Name,Comment,Block,Row,Seat\n".
            "  opening   ceremony hall 3  ,Alice,,A,1,1\n";

        $this->post(route('admin.import-bookings.propose'), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $this->assertCount(1, $props['proposal']);
        $this->assertEquals('Alice', $props['proposal'][0]['guest_name']);
    }

    /** @test */
    public function global_import_rejects_when_two_events_normalize_to_the_same_name()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats();
        Event::create([
            'name' => 'Opening Ceremony (Hall 3)',
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);
        Event::create([
            'name' => 'Opening Ceremony - Hall 3',
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(2),
            'reservation_ends_at' => Carbon::now()->addHours(1),
            'max_tickets' => 50,
        ]);

        $csv = "Event,Guest Name,Comment,Block,Row,Seat\n".
            "Opening Ceremony (Hall 3),Alice,,,,\n";

        $response = $this->post(route('admin.import-bookings.propose'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function global_import_template_has_event_column()
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.import-bookings.template'));

        $response->assertOk();
        $this->assertStringStartsWith(
            "Event,Guest Name,Comment,Block,Row,Seat,Number of Seats,Timestamp\n",
            $response->getContent()
        );
    }

    // --- Row-preferred assignment + FCFS timestamp priority ----------------------------

    /**
     * Room with two rows of 4 seats each in a single block, so a row-preferred group can
     * fit fully in a row (up to 4 seats), fully in the block (up to 8), or hit hard cap.
     */
    private function createRoomWithTwoRowsOfFour(): array
    {
        $room = Room::create(['name' => 'Row Preference Room']);
        $block = Block::create(['room_id' => $room->id, 'name' => 'A', 'type' => 'seating', 'order' => 1]);

        $row1 = Row::create(['block_id' => $block->id, 'name' => '1', 'order' => 1]);
        $row2 = Row::create(['block_id' => $block->id, 'name' => '2', 'order' => 2]);

        $row1Seats = collect(['1', '2', '3', '4'])->map(fn ($label, $i) => Seat::create(['row_id' => $row1->id, 'label' => $label, 'number' => $i + 1]));
        $row2Seats = collect(['1', '2', '3', '4'])->map(fn ($label, $i) => Seat::create(['row_id' => $row2->id, 'label' => $label, 'number' => $i + 1]));

        return [$room, $row1Seats, $row2Seats];
    }

    /** @test */
    public function row_preferred_shape_seats_the_guest_inside_their_row()
    {
        $this->actingAsAdmin();
        [$room, $row1Seats] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Row Fan,,A,1,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $guest = $props['proposal'][0];
        $this->assertCount(2, $guest['seat_ids']);
        $this->assertEmpty(array_diff($guest['seat_ids'], $row1Seats->pluck('id')->all()));
        $this->assertSame('row_preferred', $guest['assignment_strategy']);
        $this->assertSame('row', $guest['fallback_level_used']);
        $this->assertSame('A', $guest['preferred_block_name']);
        $this->assertSame('1', $guest['preferred_row_name']);
    }

    /** @test */
    public function row_preferred_falls_back_to_block_when_row_is_full()
    {
        $this->actingAsAdmin();
        [$room, $row1Seats, $row2Seats] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        // Pre-book 3 of 4 seats in row 1 so a 2-seat request can't fit contiguously there,
        // but the same block's row 2 still has plenty of room.
        foreach ($row1Seats->take(3) as $seat) {
            Booking::create([
                'event_id' => $event->id,
                'seat_id' => $seat->id,
                'user_id' => null,
                'name' => 'Prebooked',
                'type' => 'admin',
            ]);
        }

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Row Fan,,A,1,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $guest = $props['proposal'][0];
        $this->assertCount(2, $guest['seat_ids']);
        $this->assertSame('block', $guest['fallback_level_used']);
        // Row 1 only has 1 free seat - not enough for the group of 2 - but row 2 (the
        // block's next-nearest row) has plenty of room on its own, so the whole group is
        // kept together there rather than being split across the row 1/row 2 boundary.
        $this->assertSame([$row2Seats[0]->id, $row2Seats[1]->id], $guest['seat_ids']);
    }

    /** @test */
    public function row_preferred_block_fallback_prefers_rows_nearest_the_requested_row()
    {
        $this->actingAsAdmin();

        // Block A with three rows of four. The middle row (2) is the requested one.
        $room = Room::create(['name' => 'Nearest Row Room']);
        $block = Block::create(['room_id' => $room->id, 'name' => 'A', 'type' => 'seating', 'order' => 1]);

        $rows = collect([1, 2, 3])->mapWithKeys(function ($n) use ($block) {
            $row = Row::create(['block_id' => $block->id, 'name' => (string) $n, 'order' => $n]);

            return [$n => collect(['1', '2', '3', '4'])->map(fn ($label, $i) => Seat::create(['row_id' => $row->id, 'label' => $label, 'number' => $i + 1]))];
        });

        $event = $this->createEvent($room);

        // Fill the requested row 2 completely so the guest must fall back within the block.
        foreach ($rows[2] as $seat) {
            Booking::create([
                'event_id' => $event->id,
                'seat_id' => $seat->id,
                'user_id' => null,
                'name' => 'Prebooked',
                'type' => 'admin',
            ]);
        }

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Row Fan,,A,2,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        $this->assertSame('block', $guest['fallback_level_used']);
        // Row 2 (requested) is full, so the guest lands in the adjacent row 3 - not pushed
        // forward to the front row 1.
        $this->assertCount(2, $guest['seat_ids']);
        $this->assertEmpty(array_diff($guest['seat_ids'], $rows[3]->pluck('id')->all()));
        $this->assertEmpty(array_intersect($guest['seat_ids'], $rows[1]->pluck('id')->all()));
    }

    /** @test */
    public function row_preferred_block_fallback_keeps_the_group_in_one_row_instead_of_splitting_across_rows()
    {
        $this->actingAsAdmin();
        [$room, $row1Seats, $row2Seats] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        // Book row 1's first 2 seats, leaving only its last 2 (labels 3, 4) free - not enough
        // for a 3-seat request on its own. Row 2 is entirely free (4 seats, plenty for 3).
        foreach ($row1Seats->take(2) as $seat) {
            Booking::create([
                'event_id' => $event->id,
                'seat_id' => $seat->id,
                'user_id' => null,
                'name' => 'Prebooked',
                'type' => 'admin',
            ]);
        }

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Row Fan,,A,1,,3\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        $this->assertSame('block', $guest['fallback_level_used']);
        // Row 1's free tail (2 seats) alone can't fit 3 - a flat, row-spanning scan would
        // complete the run by borrowing 1 seat from the start of row 2, splitting the group
        // 2+1 across the row boundary even though row 2 alone has plenty of room. The group
        // must instead land entirely in row 2 (the next whole row that fits), not split.
        $this->assertSame(
            [$row2Seats[0]->id, $row2Seats[1]->id, $row2Seats[2]->id],
            $guest['seat_ids']
        );
    }

    /** @test */
    public function block_preferred_keeps_the_group_in_one_row_instead_of_splitting_across_rows()
    {
        $this->actingAsAdmin();
        [$room, $row1Seats, $row2Seats] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        // Same fragmentation as the row_preferred case, but with no Row specified - only
        // Block. Row 1's free tail (2 seats) alone can't fit a 3-seat request; row 2 is
        // entirely free.
        foreach ($row1Seats->take(2) as $seat) {
            Booking::create([
                'event_id' => $event->id,
                'seat_id' => $seat->id,
                'user_id' => null,
                'name' => 'Prebooked',
                'type' => 'admin',
            ]);
        }

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Block Fan,,A,,,3\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        $this->assertSame('block', $guest['fallback_level_used']);
        // Must land entirely in row 2, not split 2+1 across the row 1/row 2 boundary.
        $this->assertSame(
            [$row2Seats[0]->id, $row2Seats[1]->id, $row2Seats[2]->id],
            $guest['seat_ids']
        );
    }

    /** @test */
    public function row_preferred_leaves_guest_unresolved_when_block_is_full()
    {
        $this->actingAsAdmin();

        // Two-block room: block A (2 seats) can't fit the group, block B (4 seats) is unused.
        $room = Room::create(['name' => 'Two Block Room']);
        $blockA = Block::create(['room_id' => $room->id, 'name' => 'A', 'type' => 'seating', 'order' => 1]);
        $blockB = Block::create(['room_id' => $room->id, 'name' => 'B', 'type' => 'seating', 'order' => 2]);
        $rowA = Row::create(['block_id' => $blockA->id, 'name' => '1', 'order' => 1]);
        $rowB = Row::create(['block_id' => $blockB->id, 'name' => '1', 'order' => 1]);
        collect(['1', '2'])->map(fn ($label, $i) => Seat::create(['row_id' => $rowA->id, 'label' => $label, 'number' => $i + 1]));
        $bSeats = collect(['1', '2', '3', '4'])->map(fn ($label, $i) => Seat::create(['row_id' => $rowB->id, 'label' => $label, 'number' => $i + 1]));

        $event = $this->createEvent($room);

        // Requesting 3 seats in Block A / Row 1 — block A only has 2 seats total, so both
        // row and block scans fail; left unresolved instead of spilling into block B.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Overflow Fan,,A,1,,3\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $guest = $props['proposal'][0];
        $this->assertSame([], $guest['seat_ids']);
        $this->assertTrue($guest['unresolved']);
        $this->assertEmpty(array_intersect($guest['seat_ids'], $bSeats->pluck('id')->all()));
    }

    /** @test */
    public function row_only_preference_with_no_block_is_honored_against_the_default_center_block()
    {
        $this->actingAsAdmin();
        [$room, $leftSeats, $centerSeats, $rightSeats] = $this->createRoomWithMultipleBlocks();
        $event = $this->createEvent($room);

        // Row given, Block blank - should still land in row "1" of the default "center"
        // block, not the left/right blocks (which also have a row "1").
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Row Only Fan,,,1,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        $this->assertArrayNotHasKey('unresolved', $guest);
        $this->assertSame('row_preferred', $guest['assignment_strategy']);
        // No Block column in the CSV - the preference display only names the row, not the
        // internal default block used to place them.
        $this->assertNull($guest['preferred_block_name']);
        $this->assertSame('1', $guest['preferred_row_name']);
        $this->assertCount(2, $guest['seat_ids']);
        $this->assertEmpty(array_diff($guest['seat_ids'], $centerSeats->pluck('id')->all()));
    }

    /** @test */
    public function row_only_preference_is_unresolved_when_no_center_block_exists()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        // Row given, Block blank, and this room has no block named "center" at all - left
        // unresolved for manual placement, same as any other unmet preference.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Row Only Fan,,,2,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        $this->assertTrue($guest['unresolved']);
        $this->assertSame([], $guest['seat_ids']);
        $this->assertStringContainsString("Row '2' was not found in this room", $guest['unresolved_reason']);
    }

    /** @test */
    public function row_only_preference_is_unresolved_not_aborted_when_seats_remain_but_not_contiguously()
    {
        $this->actingAsAdmin();

        // Block named "center" (the row-only default) with two rows, so the preference
        // resolves to a real block whose own seats are just fragmented.
        $room = Room::create(['name' => 'Fragmented Room']);
        $center = Block::create(['room_id' => $room->id, 'name' => 'center', 'type' => 'seating', 'order' => 1]);
        $row1 = Row::create(['block_id' => $center->id, 'name' => '1', 'order' => 1]);
        $row2 = Row::create(['block_id' => $center->id, 'name' => '2', 'order' => 2]);
        $seatA1 = Seat::create(['row_id' => $row1->id, 'label' => '1', 'number' => 1]);
        $seatA2 = Seat::create(['row_id' => $row1->id, 'label' => '2', 'number' => 2]);
        $seatB1 = Seat::create(['row_id' => $row2->id, 'label' => '1', 'number' => 1]);
        Seat::create(['row_id' => $row2->id, 'label' => '2', 'number' => 2]);
        $event = $this->createEvent($room);

        // Book the two "middle" seats (by flattened room order) so only A1 and B2 remain
        // free - 2 free seats total, but scattered, so no contiguous run of 2 exists
        // anywhere in the block. Row given, Block blank, requesting 2 seats: the preference
        // can't be met, but since 2 free seats do still exist, this should NOT abort the
        // import - just leave the guest unresolved for manual seat picking.
        Booking::create(['event_id' => $event->id, 'seat_id' => $seatA2->id, 'user_id' => null, 'name' => 'Prebooked 1', 'type' => 'admin']);
        Booking::create(['event_id' => $event->id, 'seat_id' => $seatB1->id, 'user_id' => null, 'name' => 'Prebooked 2', 'type' => 'admin']);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Row Only Fan,,,1,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        $this->assertTrue($guest['unresolved']);
        $this->assertStringContainsString("Row '1' doesn't have 2 contiguous free seat(s)", $guest['unresolved_reason']);
        $this->assertEmpty($guest['seat_ids']);
    }

    /** @test */
    public function block_preferred_is_unresolved_not_aborted_when_the_block_is_full_but_seats_remain_elsewhere()
    {
        $this->actingAsAdmin();
        $room = Room::create(['name' => 'Fragmented Room']);
        $center = Block::create(['room_id' => $room->id, 'name' => 'center', 'type' => 'seating', 'order' => 1]);
        $centerRow = Row::create(['block_id' => $center->id, 'name' => '1', 'order' => 1]);
        $centerSeats = collect(['1', '2', '3', '4'])->map(fn ($label, $i) => Seat::create(['row_id' => $centerRow->id, 'label' => $label, 'number' => $i + 1]));
        $event = $this->createEvent($room);

        // Book seats 2 and 3, leaving only the non-adjacent 1 and 4 free - the block "is
        // full" in the sense that it can't seat 2 together, even though 2 free seats remain
        // in it. An explicit Block preference that exists but can't fit the group together
        // (here, nor anywhere else in this single-block room) should be left unresolved for
        // manual placement rather than aborting the whole import.
        Booking::create(['event_id' => $event->id, 'seat_id' => $centerSeats[1]->id, 'user_id' => null, 'name' => 'Prebooked 1', 'type' => 'admin']);
        Booking::create(['event_id' => $event->id, 'seat_id' => $centerSeats[2]->id, 'user_id' => null, 'name' => 'Prebooked 2', 'type' => 'admin']);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Block Fan,,center,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        $this->assertTrue($guest['unresolved']);
        $this->assertStringContainsString("Block 'center' doesn't have 2 contiguous free seat(s)", $guest['unresolved_reason']);
        $this->assertSame('center', $guest['preferred_block_name']);
    }

    /** @test */
    public function preference_that_genuinely_cannot_fit_anywhere_still_aborts_the_whole_import()
    {
        $this->actingAsAdmin();
        [$room] = $this->createRoomWithSeats(); // 4 physical seats, block "A" exists
        $event = $this->createEvent($room);

        // Block "A" exists (unlike the unresolved-if-nonexistent case) but the room simply
        // doesn't have 500 seats anywhere, contiguous or not - genuinely insufficient
        // capacity is the one case that still aborts the whole import.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Big Group,,A,,,500\n";

        $response = $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($error) => str_contains($error, 'Not enough available seats'));
        $this->assertNull(app(ImportSessionStore::class)->get("import_proposal:{$event->id}"));
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function oldest_timestamp_wins_the_preferred_row_when_two_guests_compete()
    {
        $this->actingAsAdmin();
        [$room, $row1Seats, $row2Seats] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        // Both guests want row 1 (4 seats total). Together they need 6, so only one
        // fits in the row - the oldest submission (Alice) should keep row 1; Bob falls back.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats,Timestamp\n".
            "Bob Later,,A,1,,3,2025-06-15 10:00:00\n".
            "Alice Earlier,,A,1,,3,2025-06-15 09:00:00\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $byName = collect($props['proposal'])->keyBy('guest_name');

        $this->assertSame('row', $byName['Alice Earlier']['fallback_level_used']);
        $this->assertCount(3, $byName['Alice Earlier']['seat_ids']);
        $this->assertEmpty(array_diff($byName['Alice Earlier']['seat_ids'], $row1Seats->pluck('id')->all()));

        // Bob had to fall back to a block-wide run (which spans into row 2 - block-
        // scoped contiguous runs can cross row edges since only availability breaks
        // the streak).
        $this->assertSame('block', $byName['Bob Later']['fallback_level_used']);
        $this->assertNotEmpty(array_intersect($byName['Bob Later']['seat_ids'], $row2Seats->pluck('id')->all()));
    }

    /** @test */
    public function guests_without_timestamp_are_served_after_timestamped_guests()
    {
        $this->actingAsAdmin();
        [$room, $row1Seats] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        // "Blank" appears first in CSV order and has no timestamp; "Timed" appears second
        // but has a valid timestamp - Timed should still win row 1 (timestamped > untimed),
        // even though they lose CSV-order tie-breaking to Blank.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats,Timestamp\n".
            "Blank Time,,A,1,,3,\n".
            "Timed,,A,1,,3,2025-06-15 09:00:00\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $byName = collect($props['proposal'])->keyBy('guest_name');

        $this->assertSame('row', $byName['Timed']['fallback_level_used']);
        $this->assertCount(3, $byName['Timed']['seat_ids']);
        $this->assertEmpty(array_diff($byName['Timed']['seat_ids'], $row1Seats->pluck('id')->all()));

        // Blank was served after Timed and no longer fits in row 1.
        $this->assertNotSame('row', $byName['Blank Time']['fallback_level_used']);
    }

    /** @test */
    public function timestamp_column_alias_submitted_at_is_recognized()
    {
        $this->actingAsAdmin();
        [$room, $row1Seats] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        // Use the "Submitted At" alias (not "Timestamp") - the older sign-up should still
        // win the preferred row.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats,Submitted At\n".
            "Later,,A,1,,3,2025-06-15 10:00:00\n".
            "Earlier,,A,1,,3,2025-06-15 09:00:00\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $byName = collect($props['proposal'])->keyBy('guest_name');

        $this->assertSame('row', $byName['Earlier']['fallback_level_used']);
        $this->assertCount(3, $byName['Earlier']['seat_ids']);
        $this->assertEmpty(array_diff($byName['Earlier']['seat_ids'], $row1Seats->pluck('id')->all()));
    }

    /** @test */
    public function unparseable_timestamp_is_treated_as_missing()
    {
        $this->actingAsAdmin();
        [$room, $row1Seats] = $this->createRoomWithTwoRowsOfFour();
        $event = $this->createEvent($room);

        // "not a date" is unparseable, treated as no timestamp - the guest with a valid
        // timestamp wins the preferred row regardless of CSV order.
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats,Timestamp\n".
            "Garbage Time,,A,1,,3,not a date\n".
            "Valid Time,,A,1,,3,2025-06-15 09:00:00\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $props = $this->previewProps($event->id);
        $byName = collect($props['proposal'])->keyBy('guest_name');

        $this->assertNull($byName['Garbage Time']['submission_timestamp']);
        $this->assertSame('row', $byName['Valid Time']['fallback_level_used']);
        $this->assertCount(3, $byName['Valid Time']['seat_ids']);
        $this->assertEmpty(array_diff($byName['Valid Time']['seat_ids'], $row1Seats->pluck('id')->all()));
    }

    /**
     * Room with a stage block at grid column 1 and three seating blocks at columns 0/1/2:
     * left (col 0), center (col 1, same column as stage), right (col 2). Each seating block
     * has one row of seats labelled 1..N in ascending seat-number order.
     */
    private function createStageRoom(): array
    {
        $room = Room::create(['name' => 'Stage Room']);

        Block::create(['room_id' => $room->id, 'name' => 'stage', 'type' => 'stage', 'position_x' => 1, 'position_y' => 0, 'order' => 0]);

        $left = Block::create(['room_id' => $room->id, 'name' => 'left', 'type' => 'seating', 'position_x' => 0, 'position_y' => 1, 'order' => 1]);
        $center = Block::create(['room_id' => $room->id, 'name' => 'center', 'type' => 'seating', 'position_x' => 1, 'position_y' => 1, 'order' => 2]);
        $right = Block::create(['room_id' => $room->id, 'name' => 'right', 'type' => 'seating', 'position_x' => 2, 'position_y' => 1, 'order' => 3]);

        $mkRow = function ($block, $count) {
            $row = Row::create(['block_id' => $block->id, 'name' => '1', 'order' => 1]);

            return collect(range(1, $count))->map(fn ($n) => Seat::create(['row_id' => $row->id, 'label' => (string) $n, 'number' => $n]));
        };

        return [$room, $mkRow($left, 4), $mkRow($center, 4), $mkRow($right, 4)];
    }

    /** @test */
    public function left_of_stage_block_fills_right_to_left()
    {
        $this->actingAsAdmin();
        [$room, $leftSeats] = $this->createStageRoom();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Lefty,,left,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        // A block left of the stage fills from the seat nearest the stage first: the two
        // highest-numbered seats (4, 3), not 1, 2.
        $this->assertSame(
            [$leftSeats[3]->id, $leftSeats[2]->id],
            $guest['seat_ids']
        );
    }

    /** @test */
    public function right_of_stage_block_fills_left_to_right()
    {
        $this->actingAsAdmin();
        [$room, , , $rightSeats] = $this->createStageRoom();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Righty,,right,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        // A block right of the stage fills nearest-the-stage first too, which for a
        // right-side block is the low-numbered seats (1, 2) - plain ascending order.
        $this->assertSame(
            [$rightSeats[0]->id, $rightSeats[1]->id],
            $guest['seat_ids']
        );
    }

    /** @test */
    public function center_block_fills_its_first_row_plainly()
    {
        $this->actingAsAdmin();
        [$room, , $centerSeats] = $this->createStageRoom();
        $event = $this->createEvent($room);

        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats\n".
            "Middle,,center,,,2\n";

        $this->post(route('admin.events.import-bookings.propose', $event->id), [
            'file' => $this->csvFile($csv),
        ])->assertRedirect(route('admin.events.import-bookings.preview', $event->id));

        $guest = $this->previewProps($event->id)['proposal'][0];

        // A block centered on the stage fills its first (front) row plainly, front to back
        // unaffected: plain ascending order (1, 2), unlike the reversed left-of-stage block.
        $this->assertSame(
            [$centerSeats[0]->id, $centerSeats[1]->id],
            $guest['seat_ids']
        );
    }

    /** @test */
    public function center_block_deeper_row_fills_from_the_emptier_edge()
    {
        $this->actingAsAdmin();

        // Center block (same column as the stage) with a full 2-seat front row and a 6-seat
        // second row where only seat 2 (first half) is already taken.
        $room = Room::create(['name' => 'Deep Center Room']);
        Block::create(['room_id' => $room->id, 'name' => 'stage', 'type' => 'stage', 'position_x' => 1, 'position_y' => 0, 'order' => 0]);
        $center = Block::create(['room_id' => $room->id, 'name' => 'center', 'type' => 'seating', 'position_x' => 1, 'position_y' => 1, 'order' => 1]);

        $frontRow = Row::create(['block_id' => $center->id, 'name' => '1', 'order' => 1]);
        $front = collect(range(1, 2))->map(fn ($n) => Seat::create(['row_id' => $frontRow->id, 'label' => (string) $n, 'number' => $n]));

        $backRow = Row::create(['block_id' => $center->id, 'name' => '2', 'order' => 2]);
        $back = collect(range(1, 6))->map(fn ($n) => Seat::create(['row_id' => $backRow->id, 'label' => (string) $n, 'number' => $n]));

        $event = $this->createEvent($room);

        // Front row full, plus the low-numbered edge of the back row taken.
        $unavailable = [$front[0]->id, $front[1]->id, $back[1]->id];

        $response = $this->postJson(route('admin.events.import-bookings.autoplace', $event->id), [
            'quantity' => 2,
            'strategy' => 'block_preferred',
            'preferred_block' => 'center',
            'preferred_row' => null,
            'unavailable' => $unavailable,
        ]);

        $response->assertOk();
        // The front row is full, so the group drops into the second row. With the low
        // (first-half) edge already occupied, a center block fills from the emptier high
        // edge instead, keeping the group on one contiguous side: seats 6 then 5.
        $response->assertJson([
            'seat_ids' => [$back[5]->id, $back[4]->id],
            'fallback_level' => 'block',
        ]);
    }

    /** @test */
    public function autoplace_endpoint_reruns_backend_assignment_for_one_guest()
    {
        $this->actingAsAdmin();
        [$room, $leftSeats] = $this->createStageRoom();
        $event = $this->createEvent($room);

        $response = $this->postJson(route('admin.events.import-bookings.autoplace', $event->id), [
            'quantity' => 2,
            'strategy' => 'block_preferred',
            'preferred_block' => 'left',
            'preferred_row' => null,
            'unavailable' => [],
        ]);

        $response->assertOk();
        // Same stage-aware order as the CSV import: left-of-stage block fills right-to-left.
        $response->assertJson([
            'seat_ids' => [$leftSeats[3]->id, $leftSeats[2]->id],
            'fallback_level' => 'block',
        ]);
    }

    /** @test */
    public function autoplace_endpoint_rejects_a_quantity_the_room_cannot_fit()
    {
        $this->actingAsAdmin();
        [$room] = $this->createStageRoom();
        $event = $this->createEvent($room);

        // The room has 12 seats total across three blocks, but no single block/room run of
        // 20 exists - the same "not enough seats" failure the CSV import hits.
        $response = $this->postJson(route('admin.events.import-bookings.autoplace', $event->id), [
            'quantity' => 20,
            'strategy' => 'none',
            'unavailable' => [],
        ]);

        $response->assertStatus(422);
    }
}
