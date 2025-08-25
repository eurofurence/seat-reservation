<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EventAdminController extends Controller
{
    public function index()
    {
        $events = Event::with(['room:id,name'])
            ->withCount('bookings')
            ->orderBy('starts_at', 'desc')
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
        $request->validate([
            'name' => 'required|string|max:255',
            'room_id' => 'required|exists:rooms,id',
            'starts_at' => 'nullable|date',
            'reservation_ends_at' => 'nullable|date',
            'max_tickets' => 'nullable|integer|min:1',
        ]);

        Event::create($request->only([
            'name',
            'room_id',
            'starts_at',
            'reservation_ends_at',
            'max_tickets',
        ]));

        return redirect()->route('admin.events.index')
            ->with('success', 'Event created successfully');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'room_id' => 'required|exists:rooms,id',
            'starts_at' => 'nullable|date',
            'reservation_ends_at' => 'nullable|date',
            'max_tickets' => 'nullable|integer|min:1',
        ]);

        $event = Event::findOrFail($id);
        $event->update($request->only([
            'name',
            'room_id',
            'starts_at',
            'reservation_ends_at',
            'max_tickets',
        ]));

        return redirect()->route('admin.events.index')
            ->with('success', 'Event updated successfully');
    }

    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return redirect()->route('admin.events.index')
            ->with('success', 'Event deleted successfully');
    }

    public function show(Request $request, $id)
    {
        $event = Event::select('id', 'name', 'starts_at', 'reservation_ends_at', 'max_tickets', 'room_id')
            ->with('room:id,name,stage_x,stage_y')
            ->findOrFail($id);

        $room = $event->room;

        // Load stage blocks for the room
        $stageBlocks = $room->stageBlocks()
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'order')
            ->orderBy('order')
            ->get();

        // Only load essential block data for the seat layout
        $blocks = $room->blocks()
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

        // Get all booked seat IDs for the seat layout
        $bookedSeats = Booking::where('event_id', $id)
            ->pluck('seat_id')
            ->toArray();

        // Get seat to booking mapping for seat clicks
        $seatBookingMap = Booking::where('event_id', $id)
            ->pluck('id', 'seat_id')
            ->toArray();

        // Build bookings query with search
        $bookingsQuery = Booking::where('event_id', $id)
            ->select('id', 'event_id', 'user_id', 'seat_id', 'name', 'comment', 'picked_up_at', 'created_at', 'booking_code')
            ->with([
                'user:id,name',
                'seat:id,row_id,label',
                'seat.row:id,block_id,name',
                'seat.row.block:id,name',
            ]);

        // Apply booking code filter if provided
        if ($bookingCode = $request->get('bookingcode')) {
            $bookingsQuery->where('booking_code', strtoupper($bookingCode));
        }
        // Apply search filter if provided (but not if booking code is set)
        elseif ($search = $request->get('search')) {
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
                    ->orWhereHas('seat.row', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('seat', function ($q) use ($search) {
                        $q->where('label', 'like', "%{$search}%");
                    });
            });
        }

        // Handle booking highlight - find the page containing the specific booking
        $currentPage = $request->get('page', 1);
        if ($bookingId = $request->get('booking_id')) {
            // Since we're ordering by latest(), count how many bookings come before this one
            $bookingPosition = $bookingsQuery->clone()
                ->whereRaw('(bookings.created_at > (SELECT created_at FROM bookings WHERE id = ?) OR (bookings.created_at = (SELECT created_at FROM bookings WHERE id = ?) AND bookings.id > ?))', [$bookingId, $bookingId, $bookingId])
                ->count();

            // Position is 1-based, so add 1. Calculate which page the booking should be on
            $targetPage = floor($bookingPosition / 10) + 1;
            $currentPage = $targetPage;
        }

        // Load bookings with pagination
        $bookings = $bookingsQuery->latest()->paginate(10, ['*'], 'page', $currentPage)->withQueryString();

        return Inertia::render('Admin/EventShow', [
            'event' => $event,
            'room' => $room,
            'blocks' => $blocks,
            'stageBlocks' => $stageBlocks,
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

    public function export($id)
    {
        $event = Event::with('room')->findOrFail($id);

        $bookings = Booking::where('event_id', $id)
            ->whereNotNull('picked_up_at')
            ->with(['user', 'seat.row.block'])
            ->get();

        $csv = "Room,Event,Name,Guest Name,Comment,Block,Row,Seat,Picked Up,Booked At\n";

        foreach ($bookings as $booking) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $this->escapeCsvField($event->room->name ?? 'N/A'),
                $this->escapeCsvField($event->name),
                $this->escapeCsvField($booking->user ? $booking->user->name : 'N/A'),
                $this->escapeCsvField($booking->name ?? 'N/A'),
                $this->escapeCsvField($booking->comment ?? ''),
                $this->escapeCsvField($booking->seat->row->block->name ?? 'N/A'),
                $this->escapeCsvField($booking->seat->row->name ?? 'N/A'),
                $this->escapeCsvField($booking->seat->label ?? 'N/A'),
                $booking->picked_up_at ? 'Yes' : 'No',
                $booking->created_at->format('Y-m-d H:i:s')
            );
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="bookings-'.$event->name.'-'.date('Y-m-d').'.csv"');
    }

    private function escapeCsvField($field)
    {
        // Escape quotes and wrap in quotes if contains comma, quote, or newline
        $field = str_replace('"', '""', $field);
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            $field = '"'.$field.'"';
        }

        return $field;
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

    public function seatingCards($id)
    {
        try {
            $event = Event::with('room')->findOrFail($id);

            $bookings = Booking::where('event_id', $id)
                ->whereNotNull('picked_up_at')
                ->with(['user:id,name', 'seat:id,row_id,label,number', 'seat.row:id,block_id,name', 'seat.row.block:id,name'])
                ->get()
                ->sortBy([
                    ['seat.row.block.name', 'asc'],
                    ['seat.row.name', 'asc'],
                    ['seat.number', 'asc'],
                ]);

            if ($bookings->isEmpty()) {
                return back()->with('error', 'No picked up bookings found for this event. Only bookings that have been picked up will generate seating cards.');
            }

            // mPDF configuration with custom Orbitron font
            $mpdf = new \Mpdf\Mpdf([
                'format' => 'A4-L',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
                'fontDir' => [resource_path('assets/fonts')],
                'fontdata' => [
                    'orbitron' => [
                        'R' => 'Orbitron-Bold.ttf',
                    ],
                ],
                'default_font' => 'orbitron',
            ]);

            // Set execution time limit for large batches
            set_time_limit(300); // 5 minutes

            // Process all bookings and generate pages
            foreach ($bookings as $index => $booking) {
                try {
                    // Use the blade template without background image
                    $html = view('pdf.seating-card-single', [
                        'booking' => $booking,
                        'event' => $event,
                    ])->render();

                    $mpdf->WriteHTML($html);

                    // Add page break after each booking except the last one
                    if ($index < $bookings->count() - 1) {
                        $mpdf->AddPage();
                    }

                } catch (\Exception $e) {
                    \Log::error("Error processing booking {$booking->id}: ".$e->getMessage());

                    continue; // Skip this booking and continue
                }
            }

            // Return PDF for browser preview
            $filename = 'seating-cards-'.\Str::slug($event->name).'-'.date('Y-m-d').'.pdf';

            return response($mpdf->Output($filename, 'S'))
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'"');

        } catch (\Exception $e) {
            \Log::error('Seating cards generation failed: '.$e->getMessage());

            return back()->with('error', 'Failed to generate seating cards: '.$e->getMessage());
        }
    }

    public function manualBooking(Request $request, $id)
    {
        $request->validate([
            'guest_name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'seat_ids' => 'required|array|min:1',
            'seat_ids.*' => 'required|integer|exists:seats,id',
        ]);

        $event = Event::findOrFail($id);

        DB::beginTransaction();

        try {
            // Check if any seats are already booked for this event
            $alreadyBooked = Booking::where('event_id', $id)
                ->whereIn('seat_id', $request->seat_ids)
                ->exists();

            if ($alreadyBooked) {
                DB::rollback();
                return back()->with('error', 'One or more selected seats are already booked.');
            }

            // Create manual bookings for all selected seats (no user_id required)
            $bookings = [];
            foreach ($request->seat_ids as $seatId) {
                $bookings[] = [
                    'type' => 'admin', // Mark as admin booking
                    'event_id' => $id,
                    'user_id' => null, // No user association for manual bookings
                    'seat_id' => $seatId,
                    'name' => $request->guest_name,
                    'comment' => $request->comment,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            Booking::insert($bookings);

            DB::commit();

            $bookingCount = count($request->seat_ids);
            $seatText = $bookingCount === 1 ? 'seat' : 'seats';

            return back()->with('success', "Successfully booked {$bookingCount} {$seatText} for {$request->name}");

        } catch (\Exception $e) {
            DB::rollback();

            return back()->with('error', 'Failed to create booking: '.$e->getMessage());
        }
    }

    public function togglePickup(Request $request, $id)
    {
        $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'picked_up' => 'required|boolean',
        ]);

        try {
            $booking = Booking::where('id', $request->booking_id)
                ->where('event_id', $id)
                ->firstOrFail();

            $booking->picked_up_at = $request->picked_up ? now() : null;
            $booking->save();

            return response()->json([
                'success' => true,
                'message' => $request->picked_up ? 'Marked as picked up' : 'Marked as not picked up',
                'picked_up_at' => $booking->picked_up_at,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update pickup status: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateBooking(Request $request, $id, $bookingId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $booking = Booking::where('id', $bookingId)
                ->where('event_id', $id)
                ->firstOrFail();

            $booking->update([
                'name' => $request->name,
                'comment' => $request->comment,
            ]);

            return back()->with('success', 'Booking updated successfully');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update booking: '.$e->getMessage());
        }
    }

    public function deleteBooking(Request $request, $id, $bookingId)
    {
        try {
            $booking = Booking::where('id', $bookingId)
                ->where('event_id', $id)
                ->firstOrFail();

            $booking->delete();

            return back()->with('success', 'Booking deleted successfully');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete booking: '.$e->getMessage());
        }
    }
}
