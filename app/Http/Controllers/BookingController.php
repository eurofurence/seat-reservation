<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookingController extends Controller
{
    private const MAX_SEATS_PER_EVENT = 2;

    public function index()
    {
        $bookings = Booking::where('user_id', auth()->id())
            ->join('events', 'bookings.event_id', '=', 'events.id')
            ->select('bookings.id', 'bookings.event_id', 'bookings.seat_id', 'bookings.name', 'bookings.comment', 'bookings.picked_up_at', 'bookings.created_at', 'bookings.booking_code')
            ->with([
                'event:id,name,starts_at,reservation_ends_at,room_id',
                'event.room:id,name',
                'seat:id,row_id,label',
                'seat.row:id,block_id,name',
                'seat.row.block:id,name',
            ])
            ->orderByRaw('bookings.picked_up_at is null desc')
            ->orderBy('events.reservation_ends_at')
            ->orderBy('events.starts_at')
            ->paginate(20);

        return Inertia::render('Booking/IndexBooking', [
            'bookings' => $bookings,
        ]);
    }

    public function create(Event $event, Request $request)
    {
        $ticketsLeft = $event->calculateTicketsLeft();

        // Check if event has tickets available
        if ($ticketsLeft <= 0) {
            return redirect()->route('events.index')
                ->with(['error' => 'This event is sold out.']);
        }

        if ($redirect = $this->denyIfBookingClosed($event, 'events.index')) {
            return $redirect;
        }

        // Check if user has reached booking limit
        if ($this->hasReachedBookingLimit($event)) {
            return redirect()->route('bookings.index')
                ->with(['error' => 'You have already booked the maximum number of seats for this event.']);
        }

        // Check if we're coming from seat selection (validation page). Users may browse the
        // seat layout before the booking window opens, but proceeding past it is still gated.
        if ($request->has('seats') && $request->has('validate')) {
            if ($redirect = $this->denyIfBookingNotOpen($event, 'bookings.create', ['event' => $event->id])) {
                return $redirect;
            }

            return $this->validateBooking($request, $event);
        }

        // Load event with room data including stage position
        $event->load('room:id,name,stage_x,stage_y');

        // Check if event has a room
        if (! $event->room) {
            return redirect()->route('events.index')
                ->with(['error' => 'This event has no room configured.']);
        }

        // Load blocks with minimal seat data - optimized to prevent memory issues
        $blocks = $event->room->blocks()
            ->with([
                'rows' => function ($query) {
                    $query->orderBy('order')->select('id', 'block_id', 'name', 'order', 'alignment');
                },
                'rows.seats' => function ($query) {
                    $query->orderBy('number')->select('id', 'row_id', 'number', 'label');
                },
            ])
            ->orderBy('order')
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'rotation', 'order')
            ->get();

        // Get already booked seats for this event efficiently. Users browsing before the
        // booking window opens shouldn't see which seats are taken, since they can't book yet.
        // Admins get no special treatment here — use the admin panel to manage bookings early.
        $isBookingOpen = $event->isBookingOpen();
        $bookedSeats = $isBookingOpen
            ? Booking::where('event_id', $event->id)->pluck('seat_id')->toArray()
            : [];

        // Load stage blocks for the room
        $stageBlocks = $event->room->stageBlocks()
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'order')
            ->orderBy('order')
            ->get();

        return Inertia::render('Booking/CreateBooking', [
            'event' => array_merge($event->only(['id', 'name', 'starts_at', 'reservation_ends_at', 'booking_starts_at']), [
                'tickets_left' => $ticketsLeft,
                'is_booking_open' => $isBookingOpen,
            ]),
            'room' => $event->room->only(['id', 'name', 'stage_x', 'stage_y']),
            'blocks' => $blocks,
            'stageBlocks' => $stageBlocks,
            'bookedSeats' => $bookedSeats,
            'selectedSeats' => $request->get('seats', []), // Pass selected seats from URL
            'maxSeatsPerUser' => min(self::MAX_SEATS_PER_EVENT, $ticketsLeft),
            'userBookedCount' => Booking::where('user_id', Auth::id())
                ->where('event_id', $event->id)
                ->count(),
        ]);
    }

    private function validateBooking(Request $request, Event $event)
    {
        $data = $request->validate([
            'seats' => 'required|array|min:1',
            'seats.*' => 'required|exists:seats,id',
        ]);

        $ticketsLeft = $event->calculateTicketsLeft();

        // Check if enough tickets are available
        if ($ticketsLeft < count($data['seats'])) {
            return redirect()->route('bookings.create', ['event' => $event->id])
                ->with(['error' => 'Not enough tickets available for this event.']);
        }

        // Check if user can book these seats
        if ($this->exceedsBookingLimit($event, count($data['seats']))) {
            return redirect()->route('bookings.create', ['event' => $event->id])
                ->with(['error' => 'You can only book a maximum of '.self::MAX_SEATS_PER_EVENT.' seats per event.']);
        }

        // Check if any seats are already booked
        $alreadyBooked = Booking::where('event_id', $event->id)
            ->whereIn('seat_id', $data['seats'])
            ->exists();

        if ($alreadyBooked) {
            // Redirect to a plain (query-string-free) URL rather than back()/referer: since
            // this page's own URL contains the conflicting seats, redirecting back to the
            // referer would just re-run this same check and loop forever.
            return redirect()->route('bookings.create', ['event' => $event->id])
                ->with(['error' => 'Sorry, one or more of your selected seats were just booked by someone else. Please choose different seats.']);
        }

        // Load seat information with minimal data
        $seats = Seat::with([
            'row:id,block_id,name',
            'row.block:id,name',
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
            'seatIds' => $data['seats'],
        ]);
    }

    public function store(Request $request, Event $event)
    {
        if ($redirect = $this->denyIfBookingClosed($event, 'bookings.index')) {
            return $redirect;
        }

        if ($redirect = $this->denyIfBookingNotOpen($event, 'bookings.index')) {
            return $redirect;
        }

        $data = $request->validate([
            'seats' => 'required|array',
            'seats.*.seat_id' => 'required|exists:seats,id',
            'seats.*.name' => 'required|string|max:24',
            'seats.*.comment' => 'nullable|string|max:255',
        ]);

        $ticketsLeft = $event->calculateTicketsLeft();

        // Check if enough tickets are available
        if ($ticketsLeft < count($data['seats'])) {
            return redirect()->route('bookings.index')
                ->with(['error' => 'Not enough tickets available for this event.']);
        }

        // Ensure user hasn't exceeded booking limit
        if ($this->exceedsBookingLimit($event, count($data['seats']))) {
            return redirect()->route('bookings.index')
                ->with(['error' => 'You can only book a maximum of '.self::MAX_SEATS_PER_EVENT.' seats per event.']);
        }

        // Use transaction to ensure atomicity
        $bookingCode = null;
        $seatConflict = false;

        DB::transaction(function () use ($event, $data, &$bookingCode, &$seatConflict) {
            // Lock seats to prevent race conditions
            $seatIds = collect($data['seats'])->pluck('seat_id')->toArray();
            Seat::whereIn('id', $seatIds)->lockForUpdate()->get();

            // Check if any seats are already booked
            $existingBookings = Booking::where('event_id', $event->id)
                ->whereIn('seat_id', $seatIds)
                ->first();

            if ($existingBookings) {
                $seatConflict = true;

                return;
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
                    'booking_code' => $bookingCode,
                ]);
            }
        });

        // Someone else grabbed one of the seats between selection and submission. Redirect to
        // the plain seat-selection URL (not back()/referer) so we don't loop on a URL whose
        // own query string still references the now-taken seats.
        if ($seatConflict) {
            return redirect()->route('bookings.create', ['event' => $event->id])
                ->with(['error' => 'Sorry, one or more of your selected seats were just booked by someone else. Please choose different seats.']);
        }

        // Redirect to confirmation page with booking code for ALL users
        if ($bookingCode) {
            return redirect()->route('bookings.confirmed', [
                'event' => $event->id,
                'code' => $bookingCode,
            ]);
        }

        return redirect()->route('bookings.index')
            ->with(['success' => 'Your booking has been confirmed!']);
    }

    public function show(Event $event, Booking $booking)
    {
        if (auth()->user()->cannot('view', $booking)) {
            return redirect()->route('bookings.index')
                ->with(['error' => 'You are not authorized to view this booking.']);
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
                'seat.row.block:id,name',
            ])
            ->find($booking->id);

        // Load blocks for seat layout (minimal data for performance)
        $blocks = $event->room->blocks()
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
                ->with(['error' => 'You are not allowed to update this booking!']);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'comment' => 'sometimes|nullable|string|max:255',
        ]);

        $booking->update($data);

        return redirect()->route('bookings.index')
            ->with(['success' => 'Booking updated!']);
    }

    public function destroy(Event $event, Booking $booking)
    {
        if (auth()->user()->cannot('delete', $booking)) {
            return redirect()->route('bookings.index')
                ->with(['error' => 'You are not allowed to cancel this booking!']);
        }

        $booking->delete();

        return redirect()->route('bookings.index')
            ->with(['success' => 'Booking cancelled!']);
    }

    /**
     * Redirect to $redirectRoute if the event's booking period hasn't opened yet. Admins get no
     * special treatment here — use the admin panel to manage bookings early.
     */
    private function denyIfBookingNotOpen(Event $event, string $redirectRoute, array $routeParams = [])
    {
        if (! $event->isBookingOpen()) {
            return redirect()->route($redirectRoute, $routeParams)
                ->with(['error' => 'Booking for this event is not yet open.']);
        }

        return null;
    }

    /**
     * Redirect to $redirectRoute if the event has ended or its reservation deadline ("locked", see
     * EventIndex.vue::getEventStatus) has passed. Admins get no special treatment here — use the
     * admin panel to manage bookings after the deadline.
     */
    private function denyIfBookingClosed(Event $event, string $redirectRoute)
    {
        if ($event->hasEnded()) {
            return redirect()->route($redirectRoute)
                ->with(['error' => 'This event has already taken place.']);
        }

        if ($event->isReservationClosed()) {
            return redirect()->route($redirectRoute)
                ->with(['error' => 'The reservation period for this event has ended.']);
        }

        return null;
    }

    /**
     * Whether the current user has already booked the max seats for this event. Admins get no
     * special treatment here — use the admin panel's manual booking to bypass the limit.
     */
    private function hasReachedBookingLimit(Event $event): bool
    {
        $existingBookings = Booking::where('user_id', Auth::id())
            ->where('event_id', $event->id)
            ->count();

        return $existingBookings >= self::MAX_SEATS_PER_EVENT;
    }

    /**
     * Whether booking $additionalSeats more seats would push the current user over the
     * limit for this event. Admins get no special treatment here — use the admin panel's
     * manual booking to bypass the limit.
     */
    private function exceedsBookingLimit(Event $event, int $additionalSeats): bool
    {
        $existingBookings = Booking::where('user_id', Auth::id())
            ->where('event_id', $event->id)
            ->count();

        return $existingBookings + $additionalSeats > self::MAX_SEATS_PER_EVENT;
    }

    /**
     * Generate a unique 3-character alphanumeric booking code
     */
    private function generateUniqueBookingCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);

        while (true) {
            $code = '';
            for ($i = 0; $i < 3; $i++) {
                $code .= $characters[rand(0, $charactersLength - 1)];
            }

            // Check if this code is currently in use
            $exists = Booking::where('booking_code', $code)->exists();

            if (! $exists) {
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
                        'block' => $booking->seat->row->block->name,
                    ],
                ];
            }),
        ]);
    }
}
