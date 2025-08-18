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
        // Check if event has tickets available
        if ($event->max_tickets && $event->max_tickets > 0) {
            $bookedTickets = $event->bookings()->count();
            if ($bookedTickets >= $event->max_tickets) {
                return redirect()->route('events.index')
                    ->with(['message' => 'This event is sold out.']);
            }
        }

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

        // Load event with room data only
        $event->load('room:id,name');

        // Load blocks with minimal seat data - optimized to prevent memory issues
        $blocks = $event->room->blocks()
            ->with([
                'rows' => function($query) {
                    $query->orderBy('sort')->select('id', 'block_id', 'name', 'sort');
                },
                'rows.seats' => function($query) {
                    $query->orderBy('sort')->select('id', 'row_id', 'sort', 'name');
                }
            ])
            ->orderBy('sort')
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'rotation', 'sort')
            ->get();

        // Get already booked seats for this event efficiently
        $bookedSeats = Booking::where('event_id', $event->id)
            ->pluck('seat_id')
            ->toArray();

        // Calculate tickets left manually to avoid heavy loading
        $maxTickets = $event->max_tickets ?? $event->tickets ?? 0;
        $bookedTickets = $event->bookings()->count();
        $ticketsLeft = max(0, $maxTickets - $bookedTickets);

        return Inertia::render('Booking/CreateBooking', [
            'event' => array_merge($event->only(['id', 'name', 'starts_at', 'reservation_ends_at']), ['tickets_left' => $ticketsLeft]),
            'room' => $event->room->only(['id', 'name']),
            'blocks' => $blocks,
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

        // Calculate tickets left manually to avoid heavy loading
        $maxTickets = $event->max_tickets ?? $event->tickets ?? 0;
        $bookedTickets = $event->bookings()->count();
        $ticketsLeft = max(0, $maxTickets - $bookedTickets);

        // Check if enough tickets are available
        if ($ticketsLeft < count($data['seats'])) {
            return redirect()->back()
                ->with(['message' => 'Not enough tickets available for this event.']);
        }

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

        // Load seat information with minimal data
        $seats = Seat::with([
                'row:id,block_id,name', 
                'row.block:id,name'
            ])
            ->select('id', 'row_id', 'name', 'sort')
            ->whereIn('id', $data['seats'])
            ->get();

        // Load room data separately to avoid heavy loading
        $room = $event->room()->select('id', 'name')->first();

        return Inertia::render('Booking/ValidateBooking', [
            'event' => $event->only(['id', 'name', 'starts_at', 'reservation_ends_at']),
            'room' => $room,
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

        // Calculate tickets left manually to avoid heavy loading
        $maxTickets = $event->max_tickets ?? $event->tickets ?? 0;
        $bookedTickets = $event->bookings()->count();
        $ticketsLeft = max(0, $maxTickets - $bookedTickets);

        // Check if enough tickets are available
        if ($ticketsLeft < count($data['seats'])) {
            return redirect()->route('bookings.index')
                ->with(['message' => 'Not enough tickets available for this event.']);
        }

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