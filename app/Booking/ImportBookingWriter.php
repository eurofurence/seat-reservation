<?php

namespace App\Booking;

use App\Models\Booking;

class ImportBookingWriter
{
    /**
     * Build the raw booking insert rows for one event's confirmed import guests.
     */
    public function rows(int $eventId, array $guests): array
    {
        $now = now();
        $rows = [];
        // Booking::generateUniqueCode() only checks already-committed rows, but every guest
        // here is inserted in one batch after this loop finishes - track codes handed out
        // within this call too, so two guests in the same import can't collide with each other.
        $usedCodes = [];

        foreach ($guests as $guest) {
            // All seats booked for one guest share one code, same as a self-service booking.
            do {
                $bookingCode = Booking::generateUniqueCode();
            } while (in_array($bookingCode, $usedCodes, true));
            $usedCodes[] = $bookingCode;

            foreach ($guest['seat_ids'] as $seatId) {
                $rows[] = [
                    'type' => 'admin',
                    'event_id' => $eventId,
                    'user_id' => null,
                    'seat_id' => $seatId,
                    'name' => $guest['guest_name'],
                    'comment' => $guest['comment'] ?? null,
                    'booking_code' => $bookingCode,
                    'picked_up_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }
}
