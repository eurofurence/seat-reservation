<?php

namespace App\Booking;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

class RoomLayoutLoader
{
    /**
     * Shared room/blocks/stageBlocks/bookedSeats loading for the seat-layout map,
     * used by the event show, import preview and customer seat-selection pages.
     */
    public function load(Event $event): array
    {
        $room = $event->room;

        $bookedSeats = Booking::where('event_id', $event->id)
            ->pluck('seat_id')
            ->toArray();

        return [$room, $this->blocks($room), $this->stageBlocks($room), $bookedSeats];
    }

    /**
     * Seating blocks with rows and seats, selecting only the fields the seat map renders.
     */
    public function blocks(Room $room): Collection
    {
        return $room->blocks()
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'rotation', 'order')
            ->with(['rows' => function ($query) {
                $query->select('id', 'block_id', 'name', 'order', 'alignment')
                    ->orderBy('order');
                $query->with(['seats' => function ($q) {
                    $q->select('id', 'row_id', 'label', 'number')
                        ->orderBy('number');
                }]);
            }])
            ->orderBy('order')
            ->get();
    }

    public function stageBlocks(Room $room): Collection
    {
        return $room->stageBlocks()
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'order')
            ->orderBy('order')
            ->get();
    }
}
