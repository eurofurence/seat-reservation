<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function index()
    {
        return Inertia::render('Booking/IndexBooking',[
            'bookings' => Booking::with('event.room', 'seat.row.block')
                ->where('user_id', auth()->id())
                ->get(),
        ]);
    }

    public function create(Event $event, Request $request)
    {
        // Check if user has reached booking limit
        if (!Auth::user()->is_admin) {
            $existingBookings = Booking::where('user_id', Auth::id())
                ->where('event_id', $event->id)
                ->count();
            
            if ($existingBookings >= 2) {
                return redirect()->route('bookings.index')
                    ->with(['message' => 'You have already booked the maximum number of seats for this event.']);
            }
        }

        // Check if we're coming from seat selection (validation page)
        if ($request->has('seats') && $request->has('validate')) {
            return $this->validateBooking($request, $event);
        }

        // Load event with all seat data
        $event->load([
            'room.blocks.rows.seats'
        ]);

        // Get already booked seats for this event
        $bookedSeats = Booking::where('event_id', $event->id)
            ->pluck('seat_id')
            ->toArray();

        return Inertia::render('Booking/CreateBooking', [
            'event' => $event,
            'room' => $event->room,
            'blocks' => $event->room->blocks,
            'bookedSeats' => $bookedSeats,
            'maxSeatsPerUser' => Auth::user()->is_admin ? 999 : 2,
            'userBookedCount' => Auth::user()->is_admin ? 0 : Booking::where('user_id', Auth::id())
                ->where('event_id', $event->id)
                ->count()
        ]);
    }

    private function validateBooking(Request $request, Event $event)
    {
        $data = $request->validate([
            'seats' => 'required|array|min:1',
            'seats.*' => 'required|exists:seats,id'
        ]);

        // Check if user can book these seats
        if (!Auth::user()->is_admin) {
            $existingBookings = Booking::where('user_id', Auth::id())
                ->where('event_id', $event->id)
                ->count();
            
            if ($existingBookings + count($data['seats']) > 2) {
                return redirect()->back()
                    ->with(['message' => 'You can only book a maximum of 2 seats per event.']);
            }
        }

        // Check if any seats are already booked
        $alreadyBooked = Booking::where('event_id', $event->id)
            ->whereIn('seat_id', $data['seats'])
            ->exists();

        if ($alreadyBooked) {
            return redirect()->back()
                ->with(['message' => 'Some of the selected seats are already booked.']);
        }

        // Load seat information
        $seats = Seat::with('row.block')
            ->whereIn('id', $data['seats'])
            ->get();

        return Inertia::render('Booking/ValidateBooking', [
            'event' => $event->load('room'),
            'seats' => $seats,
            'seatIds' => $data['seats']
        ]);
    }

    public function store(Request $request, Event $event)
    {
        if ($event->reservation_ends_at->isPast()) {
            return redirect()->route('bookings.index')
                ->with(['message' => 'The reservation period for this event has ended.']);
        }

        $data = $request->validate([
            'seats' => 'required|array',
            'seats.*.seat_id' => 'required|exists:seats,id',
            'seats.*.name' => 'required|string|max:255',
            'seats.*.comment' => 'nullable|string|max:255'
        ]);

        // Ensure user hasn't exceeded booking limit
        if (!Auth::user()->is_admin) {
            $existingBookings = Booking::where('user_id', Auth::id())
                ->where('event_id', $event->id)
                ->count();
            
            if ($existingBookings + count($data['seats']) > 2) {
                return redirect()->route('bookings.index')
                    ->with(['message' => 'You can only book a maximum of 2 seats per event.']);
            }
        }

        // Use transaction to ensure atomicity
        DB::transaction(function () use ($event, $data) {
            // Lock seats to prevent race conditions
            $seatIds = collect($data['seats'])->pluck('seat_id')->toArray();
            Seat::whereIn('id', $seatIds)->lockForUpdate()->get();

            // Check if any seats are already booked
            $existingBookings = Booking::where('event_id', $event->id)
                ->whereIn('seat_id', $seatIds)
                ->first();

            if ($existingBookings) {
                throw ValidationException::withMessages([
                    'seats' => 'Some of the selected seats have already been booked.'
                ]);
            }

            // Create bookings
            foreach ($data['seats'] as $seatData) {
                $event->bookings()->create([
                    'user_id' => auth()->id(),
                    'seat_id' => $seatData['seat_id'],
                    'name' => $seatData['name'],
                    'comment' => $seatData['comment'] ?? null,
                    'type' => 'online'
                ]);
            }
        });

        return redirect()->route('bookings.index')
            ->with(['message' => 'Your booking has been confirmed!']);
    }

    public function show(Event $event, Booking $booking)
    {
        if (auth()->user()->cannot('view', $booking)) {
            abort(403);
        }
        
        return Inertia::render('Booking/ShowBooking', [
            'event' => $event->load('room'),
            'booking' => $booking->load('event.room', 'seat.row.block'),
        ]);
    }

    public function update(Event $event, Booking $booking, Request $request)
    {
        if ($request->user()->cannot('update', $booking)) {
            return redirect()->route('bookings.index')
                ->with(['message' => 'You are not allowed to update this booking!']);
        }
        
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'comment' => 'sometimes|nullable|string|max:255',
            'ticket_given' => 'sometimes|boolean',
        ]);
        
        $booking->update($data);
        
        return redirect()->route('bookings.index')
            ->with(['message' => 'Booking updated!']);
    }

    public function destroy(Event $event, Booking $booking)
    {
        if (auth()->user()->cannot('delete', $booking)) {
            return redirect()->route('bookings.index')
                ->with(['message' => 'You are not allowed to cancel this booking!']);
        }
        
        $booking->delete();
        
        return redirect()->route('bookings.index')
            ->with(['message' => 'Booking cancelled!']);
    }
}