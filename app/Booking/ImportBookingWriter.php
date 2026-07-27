<?php

namespace App\Booking;

class ImportBookingWriter
{
    /**
     * Build the raw booking insert rows for one event's confirmed import guests.
     */
    public function rows(int $eventId, array $guests): array
    {
        $now = now();
        $rows = [];

        foreach ($guests as $guest) {
            foreach ($guest['seat_ids'] as $seatId) {
                $rows[] = [
                    'type' => 'admin',
                    'event_id' => $eventId,
                    'user_id' => null,
                    'seat_id' => $seatId,
                    'name' => $guest['guest_name'],
                    'comment' => $guest['comment'] ?? null,
                    'picked_up_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }
}
