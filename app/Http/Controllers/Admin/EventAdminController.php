<?php

namespace App\Http\Controllers\Admin;

use App\Booking\RoomLayoutLoader;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EventAdminController extends Controller
{
    public function index()
    {
        $events = Event::with(['room:id,name'])
            ->withCount('bookings')
            ->orderBy('starts_at')
            ->get();

        $rooms = Room::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/EventIndex', [
            'events' => $events,
            'rooms' => $rooms,
            'title' => 'Events',
            'breadcrumbs' => [],
        ]);
    }

    public function store(Request $request)
    {
        Event::create($this->validatedEventData($request));

        return redirect()->route('admin.events.index')
            ->with('success', 'Event created successfully');
    }

    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $data = $this->validatedEventData($request);

        if ((int) $data['room_id'] !== (int) $event->room_id
            && Booking::where('event_id', $event->id)->exists()) {
            throw ValidationException::withMessages([
                'room_id' => 'The room cannot be changed because this event already has bookings.',
            ]);
        }

        $event->update($data);

        return redirect()->route('admin.events.index')
            ->with('success', 'Event updated successfully');
    }

    private function validatedEventData(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'room_id' => 'required|exists:rooms,id',
            'starts_at' => 'nullable|date',
            'reservation_ends_at' => 'nullable|date',
            'booking_starts_at' => 'nullable|date',
            'max_tickets' => 'nullable|integer|min:1',
        ]);

        $startsAt = $validated['starts_at'] ?? null;
        $reservationEndsAt = $validated['reservation_ends_at'] ?? null;
        $bookingStartsAt = $validated['booking_starts_at'] ?? null;

        $errors = [];

        if ($startsAt && $reservationEndsAt && Carbon::parse($reservationEndsAt)->gt(Carbon::parse($startsAt))) {
            $errors['reservation_ends_at'] = 'The reservation deadline must be before the event start date.';
        }

        if ($reservationEndsAt && $bookingStartsAt && Carbon::parse($bookingStartsAt)->gt(Carbon::parse($reservationEndsAt))) {
            $errors['booking_starts_at'] = 'The booking start date must be before the reservation deadline.';
        }

        if ($startsAt && $bookingStartsAt && Carbon::parse($bookingStartsAt)->gt(Carbon::parse($startsAt))) {
            $errors['booking_starts_at'] = 'The booking start date must be before the event start date.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $request->only([
            'name',
            'room_id',
            'starts_at',
            'reservation_ends_at',
            'booking_starts_at',
            'max_tickets',
        ]);
    }

    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return redirect()->route('admin.events.index')
            ->with('success', 'Event deleted successfully');
    }

    public function show(Request $request, $id, RoomLayoutLoader $layout)
    {
        $event = Event::select('id', 'name', 'starts_at', 'reservation_ends_at', 'booking_starts_at', 'max_tickets', 'room_id')
            ->with('room:id,name,stage_x,stage_y')
            ->findOrFail($id);

        [$room, $blocks, $stageBlocks, $bookedSeats] = $layout->load($event);

        $markerBlocks = $room->markerBlocks()
            ->select('id', 'room_id', 'name', 'type', 'position_x', 'position_y', 'rotation', 'colspan', 'rowspan', 'order')
            ->get();

        // Get seat to booking mapping for seat clicks
        $seatBookingMap = Booking::where('event_id', $id)
            ->pluck('id', 'seat_id')
            ->toArray();

        // Build bookings query with search
        $bookingsQuery = Booking::where('event_id', $id)
            ->select('id', 'event_id', 'user_id', 'created_by_name', 'seat_id', 'name', 'comment', 'picked_up_at', 'created_at', 'booking_code', 'type')
            ->withSeatDetails()
            ->with('user:id,name');

        // Apply booking code filter if provided
        if ($bookingCode = $request->get('bookingcode')) {
            $bookingsQuery->where('booking_code', strtoupper($bookingCode));
        }
        // Apply search filter if provided (but not if booking code is set)
        elseif (is_string($search = $request->get('search')) && $search !== '') {
            $bookingsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhere('booking_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('seat.row.block', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('seat', function ($q) use ($search) {
                        $q->where('label', 'like', "%{$search}%");
                    });

                // Only match rows when the search includes a number, so a bare
                // "Row" doesn't return every booking in the event.
                if (preg_match('/\d/', $search)) {
                    $query->orWhereHas('seat.row', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                }
            });
        }

        // Handle booking highlight - find the page containing the specific booking
        $currentPage = $request->get('page', 1);
        if ($bookingId = $request->get('booking_id')) {
            $bookingPosition = $bookingsQuery->clone()
                ->where('id', '>', $bookingId)
                ->count();

            $currentPage = intdiv($bookingPosition, 10) + 1;
        }

        // Load bookings with pagination
        $bookings = $bookingsQuery->orderByDesc('id')->paginate(10, ['*'], 'page', $currentPage)->withQueryString();

        return Inertia::render('Admin/EventShow', [
            'event' => $event,
            'room' => $room,
            'blocks' => $blocks,
            'stageBlocks' => $stageBlocks,
            'markerBlocks' => $markerBlocks,
            'bookings' => $bookings,
            'bookedSeats' => $bookedSeats,
            'seatBookingMap' => $seatBookingMap,
            'search' => $request->get('search', ''),
            'bookingcode' => $request->get('bookingcode', ''),
            'booking_id' => $request->get('booking_id'),
            'selected_seats' => $this->getSelectedSeatsParameter($request),
            'title' => $event->name,
            'breadcrumbs' => [
                ['title' => 'Events', 'url' => route('admin.events.index')],
                ['title' => $event->name, 'url' => null],
            ],
        ]);
    }

    private function getSelectedSeatsParameter($request)
    {
        // Handle both formats: selected_seats=1,2,3 and seats[]=1&seats[]=2&seats[]=3
        if ($request->has('selected_seats')) {
            return $request->get('selected_seats', '');
        }

        if ($request->has('seats')) {
            $seats = $request->get('seats', []);

            return is_array($seats) ? implode(',', $seats) : '';
        }

        return '';
    }
}
