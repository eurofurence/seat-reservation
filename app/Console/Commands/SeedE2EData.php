<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

// ponytail: deterministic fixture builder for Playwright e2e runs only (guarded by
// name prefix "E2E "), not meant as a general-purpose seeder. Wipes + recreates its
// own rows each run so specs never depend on leftover state.
class SeedE2EData extends Command
{
    protected $signature = 'e2e:seed';

    protected $description = 'Reset and (re)create deterministic Room/Event/Block/Row/Seat fixtures for Playwright tests';

    public function handle(): int
    {
        Room::where('name', 'E2E Test Room')->get()->each->delete();

        $room = Room::create([
            'name' => 'E2E Test Room',
            'stage_x' => 1,
            'stage_y' => 0,
        ]);

        $block = Block::create([
            'room_id' => $room->id,
            'name' => 'Block A',
            'type' => 'seating',
            'position_x' => 1,
            'position_y' => 1,
            'rotation' => 0,
            'order' => 0,
        ]);

        $seatsByLabel = [];

        foreach (['Row 1', 'Row 2'] as $i => $rowName) {
            $row = Row::create([
                'block_id' => $block->id,
                'name' => $rowName,
                'order' => $i + 1,
                'seats_count' => 5,
                'custom_seat_count' => null,
            ]);

            for ($seatNumber = 1; $seatNumber <= 5; $seatNumber++) {
                $seat = Seat::create([
                    'row_id' => $row->id,
                    'number' => $seatNumber,
                    'label' => (string) $seatNumber,
                ]);

                $seatsByLabel["{$rowName}-{$seatNumber}"] = $seat;
            }
        }

        // Booking window open, event/deadline in the future -> the happy path. No filler
        // booking here: user/booking-flow.spec.ts and user/seat-selection.spec.ts each book
        // and/or select real seats on this event (per-user cap is 2, shared dev-login user) -
        // keep it seat-collision-free for them. The seat-conflict-redirect spec uses
        // "Row 1-5" and admin/manual-booking.spec.ts's pickup-toggle test uses "Row 2-1",
        // which those specs don't touch either.
        $openEvent = Event::create([
            'room_id' => $room->id,
            'name' => 'E2E Test Event',
            'max_tickets' => 10,
            'starts_at' => Carbon::now()->addDays(7),
            'booking_starts_at' => Carbon::now()->subDay(),
            'reservation_ends_at' => Carbon::now()->addDays(6),
        ]);

        // Same room, max_tickets already met by a filler booking -> booking/create()
        // redirects to events.index with a "sold out" message.
        $soldOutEvent = Event::create([
            'room_id' => $room->id,
            'name' => 'E2E Sold Out Event',
            'max_tickets' => 1,
            'starts_at' => Carbon::now()->addDays(7),
            'booking_starts_at' => Carbon::now()->subDay(),
            'reservation_ends_at' => Carbon::now()->addDays(6),
        ]);

        Booking::create([
            'event_id' => $soldOutEvent->id,
            'seat_id' => $seatsByLabel['Row 1-1']->id,
            'user_id' => null,
            'type' => 'admin',
            'name' => 'Filler Booking',
        ]);

        // booking_starts_at in the future -> BookingController::create() still renders the seat
        // picker (browsing allowed) but with is_booking_open=false: banner shown, booked seats
        // hidden, "Continue" gated, sidebar shows the booking-opens time. A filler booking
        // proves it stays hidden while the window isn't open yet.
        $notOpenEvent = Event::create([
            'room_id' => $room->id,
            'name' => 'E2E Not Open Event',
            'max_tickets' => 10,
            'starts_at' => Carbon::now()->addDays(14),
            'booking_starts_at' => Carbon::now()->addDay(),
            'reservation_ends_at' => Carbon::now()->addDays(13),
        ]);

        Booking::create([
            'event_id' => $notOpenEvent->id,
            'seat_id' => $seatsByLabel['Row 1-1']->id,
            'user_id' => null,
            'type' => 'admin',
            'name' => 'Filler Booking',
        ]);

        // starts_at already in the past -> Event::hasEnded() guard. Customer-facing booking
        // (including admins going through it) must be blocked at create(); admin panel manual
        // booking must still work.
        $endedEvent = Event::create([
            'room_id' => $room->id,
            'name' => 'E2E Ended Event',
            'max_tickets' => 10,
            'starts_at' => Carbon::now()->subHour(),
            'booking_starts_at' => Carbon::now()->subDays(2),
            'reservation_ends_at' => Carbon::now()->subHour(),
        ]);

        // reservation_ends_at already in the past but starts_at still in the future ->
        // Event::isReservationClosed() guard (distinct from hasEnded()). Same bypass rules
        // as the ended event above.
        $closedEvent = Event::create([
            'room_id' => $room->id,
            'name' => 'E2E Closed Reservation Event',
            'max_tickets' => 10,
            'starts_at' => Carbon::now()->addDays(7),
            'booking_starts_at' => Carbon::now()->subDays(2),
            'reservation_ends_at' => Carbon::now()->subHour(),
        ]);

        $this->info(
            "Seeded room #{$room->id}: open event #{$openEvent->id}, sold-out event #{$soldOutEvent->id}, ".
            "not-open event #{$notOpenEvent->id}, ended event #{$endedEvent->id}, closed event #{$closedEvent->id} for e2e tests."
        );

        return self::SUCCESS;
    }
}
