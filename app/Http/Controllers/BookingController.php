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
            'bookings' => Booking::with('event.room','seat.row.block')->where('user_id', auth()->id())->get(),
        ]);
    }

    public function create(Event $event, Request $request)
    {
        $data = $request->validate([
            'seats.*' => 'numeric|nullable',
            'verifyBooking' => 'boolean|nullable',
        ]);
        if (!Auth::user()->is_admin) {
            $bookings = Booking::where('user_id', Auth::id())->where('event_id', $event->id)->count();
            if ($bookings >= 2) {
                return redirect()->route('bookings.index')->with(['message' => 'You can only book 2 seats per event.']);
            }
        }
        if (
            isset($data['seats'], $data['verifyBooking']) && $data['verifyBooking'] === "1" && count($data['seats']) > 0) {
            return Inertia::render('Booking/VerifyBooking', [
                'event' => $event->loadMissing('room.blocks.rows.seats'),
                'seats' => Seat::with('row.block')->findMany($data['seats']),
                'seatsFalltrough' => $data['seats']
            ]);
        }
        return Inertia::render('Booking/CreateBooking', [
            'event' => $event->loadMissing('room.blocks.rows.seats'),
            'seats' => collect($data['seats'] ?? [])->map(fn($s) => (int) $s)->values()->toArray() ?? [],
            'takenSeats' => $event->bookings()->pluck('seat_id')->values()->toArray(),
            'availableToUser' => (Auth::user()->is_admin) ? 9999999 : 2 - $event->bookings()->where('user_id', Auth::user()->id)->count(),
        ]);
    }

    public function store(Request $request, Event $event)
    {
        if ($event->reservation_ends_at->isPast()) {
            return redirect()->route('bookings.index')->with(['message' => 'The reservation period for this event has ended.']);
        }
        $data = $request->validate([
            'seats.*.name' => 'required|string|max:255',
            'seats.*.comment' => 'nullable|string|max:255',
            'seats.*.seat_id' => 'required|numeric|exists:seats,id',
        ]);
        // Ensure that user is not booking more than 2 seats
        if (!Auth::user()->is_admin) {
            $bookings = Booking::where('user_id', Auth::id())->where('event_id', $event->id)->count();
            if ($bookings + count($data['seats']) > 2) {
                return redirect()->route('bookings.index')->with(['message' => 'You can only book 2 seats per event.']);
            }
            // Allow no more than max seats
            if ($event->seats_left <= 0) {
                return redirect()->route('bookings.index')->with(['message' => 'There are no seats available.']);
            }
        }
        // Start DB Transaction
        DB::transaction(function () use ($event, $data) {
            // Lock seats
            Seat::whereIn('id', collect($data['seats'])->pluck('seat_id'))->sharedLock()->get();
            // Check if any of the seats have a booking for the event
            $existingBookings = Booking::whereIn('seat_id',
                collect($data['seats'])->pluck('seat_id'))->where('event_id', $event->id)->first();
            if ($existingBookings !== null) {
                throw ValidationException::withMessages(['seats' => 'Some of the seats are already booked. Please return to the previous page and select other seats.']);
            }
            // Create bookings
            $event->bookings()->createMany(collect($data['seats'])->map(fn($s) => [
                'user_id' => auth()->id(),
                'seat_id' => $s['seat_id'],
                'name' => $s['name'],
                'comment' => $s['comment'],
            ])->toArray());
        }, 5);
        // End DB Transaction
        return redirect()->route('bookings.index')->with(['message' => 'Your booking has been created!']);

    }

    public function show(Event $event, Booking $booking)
    {
        if (auth()->user()->cannot('view', $booking)) {
            abort(403);
        }
        return Inertia::render('Booking/ShowBooking', [
            'event' => $event->loadMissing('room.blocks.rows.seats'),
            'booking' => $booking->loadMissing('seat.row.block','event.room'),
        ]);
    }

    public function update(Event $event, Booking $booking, Request $request)
    {
        if ($request->user()->cannot('update', $booking)) {
            return redirect()->route('bookings.index')->with(['message' => 'You are not allowed to update this booking!']);
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:255',
        ]);
        $booking->update($data);
        return redirect()->route('bookings.index')->with(['message' => 'Booking updated!']);
    }

    public function destroy(Event $event,Booking $booking)
    {
        if (auth()->user()->cannot('delete', $booking)) {
            return redirect()->route('bookings.index')->with(['message' => 'You are not allowed to cancel this booking!']);
        }
        $booking->delete();
        return redirect()->route('bookings.index')->with(['message' => 'Booking cancelled!']);
    }
}
