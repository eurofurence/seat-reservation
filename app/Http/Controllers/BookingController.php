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
        $bookings = Booking::where('user_id', auth()->id())
            ->select('id', 'event_id', 'seat_id', 'name', 'comment', 'picked_up_at', 'created_at', 'booking_code')
            ->with([
                'event:id,name,starts_at,reservation_ends_at,room_id',
                'event.room:id,name',
                'seat:id,row_id,label',
                'seat.row:id,block_id,name',
                'seat.row.block:id,name'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Booking/IndexBooking', [
            'bookings' => $bookings,
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

        // Load event with room data including stage position
        $event->load('room:id,name,stage_x,stage_y');

        // Check if event has a room
        if (!$event->room) {
            return redirect()->route('events.index')
                ->with(['error' => 'This event has no room configured.']);
        }

        // Load blocks with minimal seat data - optimized to prevent memory issues
        $blocks = $event->room->blocks()
            ->with([
                'rows' => function($query) {
                    $query->orderBy('order')->select('id', 'block_id', 'name', 'order');
                },
                'rows.seats' => function($query) {
                    $query->orderBy('number')->select('id', 'row_id', 'number', 'label');
                }
            ])
            ->orderBy('order')
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'rotation', 'order')
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
            'room' => $event->room->only(['id', 'name', 'stage_x', 'stage_y']),
            'blocks' => $blocks,
            'bookedSeats' => $bookedSeats,
            'selectedSeats' => $request->get('seats', []), // Pass selected seats from URL
            'maxSeatsPerUser' => 2,
            'userBookedCount' => Booking::where('user_id', Auth::id())
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
            ->select('id', 'row_id', 'label', 'number')
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
        $bookingCode = null;
        
        DB::transaction(function () use ($event, $data, &$bookingCode) {
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

            // Generate unique booking code for ALL bookings through user interface
            $bookingCode = $this->generateUniqueBookingCode();

            // Create bookings
            foreach ($data['seats'] as $seatData) {
                $event->bookings()->create([
                    'user_id' => auth()->id(),
                    'seat_id' => $seatData['seat_id'],
                    'name' => $seatData['name'],
                    'comment' => $seatData['comment'] ?? null,
                    'type' => 'online',
                    'booking_code' => $bookingCode
                ]);
            }
        });

        // Redirect to confirmation page with booking code for ALL users
        if ($bookingCode) {
            return redirect()->route('bookings.confirmed', [
                'event' => $event->id,
                'code' => $bookingCode
            ]);
        }

        return redirect()->route('bookings.index')
            ->with(['message' => 'Your booking has been confirmed!']);
    }

    public function show(Event $event, Booking $booking)
    {
        if (auth()->user()->cannot('view', $booking)) {
            abort(403);
        }
        
        // Load event with room including stage coordinates for seat layout
        $event = Event::select('id', 'name', 'starts_at', 'reservation_ends_at', 'room_id')
            ->with('room:id,name,stage_x,stage_y')
            ->find($event->id);
            
        $booking = Booking::select('id', 'event_id', 'user_id', 'seat_id', 'name', 'comment', 'picked_up_at', 'created_at', 'booking_code')
            ->with([
                'event:id,name,starts_at,reservation_ends_at,room_id',
                'event.room:id,name,stage_x,stage_y',
                'seat:id,row_id,label',
                'seat.row:id,block_id,name',
                'seat.row.block:id,name'
            ])
            ->find($booking->id);
            
        // Load blocks for seat layout (minimal data for performance)
        $blocks = $event->room->blocks()
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'rotation', 'order')
            ->with(['rows' => function($query) {
                $query->select('id', 'block_id', 'name', 'order')
                    ->orderBy('order');
                $query->with(['seats' => function($q) {
                    $q->select('id', 'row_id', 'label', 'number')
                        ->orderBy('number');
                }]);
            }])
            ->orderBy('order')
            ->get();
            
        // Get user's booked seat IDs for highlighting
        $userBookedSeats = Booking::where('event_id', $event->id)
            ->where('user_id', auth()->id())
            ->pluck('seat_id')
            ->toArray();
            
        // Get all other booked seat IDs (excluding user's seats) for this event
        $bookedSeats = Booking::where('event_id', $event->id)
            ->where('user_id', '!=', auth()->id())
            ->pluck('seat_id')
            ->toArray();
        
        return Inertia::render('Booking/ShowBooking', [
            'event' => $event,
            'booking' => $booking,
            'blocks' => $blocks,
            'bookedSeats' => $bookedSeats,
            'userBookedSeats' => $userBookedSeats,
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

    /**
     * Generate a unique 2-character alphanumeric booking code
     */
    private function generateUniqueBookingCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        
        while (true) {
            $code = '';
            for ($i = 0; $i < 2; $i++) {
                $code .= $characters[rand(0, $charactersLength - 1)];
            }
            
            // Check if this code is currently in use
            $exists = Booking::where('booking_code', $code)->exists();
            
            if (!$exists) {
                return $code;
            }
        }
    }

    /**
     * Show booking confirmation page
     */
    public function confirmed(Event $event, $code)
    {
        // Verify the booking code belongs to the current user
        $bookings = Booking::where('event_id', $event->id)
            ->where('booking_code', $code)
            ->where('user_id', auth()->id())
            ->with(['seat.row.block'])
            ->get();

        if ($bookings->isEmpty()) {
            return redirect()->route('bookings.index');
        }

        return Inertia::render('Booking/BookingConfirmed', [
            'event' => $event->only(['id', 'name', 'reservation_ends_at']),
            'bookingCode' => $code,
            'bookings' => $bookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'name' => $booking->name,
                    'seat' => [
                        'label' => $booking->seat->label,
                        'row' => $booking->seat->row->name,
                        'block' => $booking->seat->row->block->name
                    ]
                ];
            })
        ]);
    }
}