<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualBookingController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $request->validate([
            'guest_name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'seat_ids' => 'required|array|min:1',
            'seat_ids.*' => 'required|integer|exists:seats,id',
        ]);

        $event = Event::findOrFail($id);
        $seatIds = $request->seat_ids;

        // Every seat must belong to this event's room - a tampered payload could otherwise
        // book seats from another room against this event and corrupt seating data.
        $roomSeatCount = Seat::whereHas('row.block', fn ($q) => $q->where('room_id', $event->room_id))
            ->whereIn('id', $seatIds)
            ->count();

        if ($roomSeatCount !== count($seatIds)) {
            return back()->with('error', "One or more selected seats don't belong to this event's room.");
        }

        $created = DB::transaction(function () use ($seatIds, $request, $id) {
            // Lock the seat rows so a concurrent booking can't race us into the
            // (event_id, seat_id) unique index between the exists() check and insert().
            Seat::whereIn('id', $seatIds)->lockForUpdate()->get();

            $alreadyBooked = Booking::where('event_id', $id)
                ->whereIn('seat_id', $seatIds)
                ->exists();

            if ($alreadyBooked) {
                return false;
            }

            // Create manual bookings for all selected seats (no user_id, no booking code)
            Booking::insert(collect($seatIds)->map(fn ($seatId) => [
                'type' => 'admin',
                'event_id' => $id,
                'user_id' => null,
                'seat_id' => $seatId,
                'name' => $request->guest_name,
                'comment' => $request->comment,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            return true;
        });

        if (! $created) {
            return back()->with('error', 'One or more selected seats are already booked.');
        }

        $bookingCount = count($seatIds);
        $seatText = $bookingCount === 1 ? 'seat' : 'seats';

        return back()->with('success', "Successfully booked {$bookingCount} {$seatText} for {$request->guest_name}");
    }
}
