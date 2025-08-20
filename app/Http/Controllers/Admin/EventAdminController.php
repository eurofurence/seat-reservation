<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Room;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

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
            'breadcrumbs' => []
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
            'max_tickets'
        ]));
        
        return redirect()->route('admin.events.index')
            ->with('success', 'Event created successfully');
    }
    
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();
        
        return redirect()->route('admin.events.index')
            ->with('success', 'Event deleted successfully');
    }

    public function show($id)
    {
        $event = Event::select('id', 'name', 'starts_at', 'room_id')
            ->with('room:id,name,stage_x,stage_y')
            ->findOrFail($id);
        
        $room = $event->room;
        
        // Only load essential block data for the seat layout
        $blocks = $room->blocks()
            ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'rotation', 'sort')
            ->with(['rows' => function($query) {
                $query->select('id', 'block_id', 'name', 'sort')
                    ->orderBy('sort');
                $query->with(['seats' => function($q) {
                    $q->select('id', 'row_id', 'label', 'number', 'sort')
                        ->orderBy('sort');
                }]);
            }])
            ->orderBy('sort')
            ->get();
        
        // Get all booked seat IDs for the seat layout
        $bookedSeats = Booking::where('event_id', $id)
            ->pluck('seat_id')
            ->toArray();
        
        // Load bookings with minimal user data - paginated for the table
        $bookings = Booking::where('event_id', $id)
            ->select('id', 'event_id', 'user_id', 'seat_id', 'created_at')
            ->with([
                'user:id,name,email',
                'seat:id,row_id,label',
                'seat.row:id,block_id,name',
                'seat.row.block:id,name'
            ])
            ->latest()
            ->paginate(50);
        
        return Inertia::render('Admin/EventShow', [
            'event' => $event,
            'room' => $room,
            'blocks' => $blocks,
            'bookings' => $bookings,
            'bookedSeats' => $bookedSeats,
            'title' => $event->name,
            'breadcrumbs' => [
                ['title' => 'Events', 'url' => route('admin.events.index')],
                ['title' => $event->name, 'url' => null]
            ]
        ]);
    }
    
    public function export($id)
    {
        $event = Event::findOrFail($id);
        
        $bookings = Booking::where('event_id', $id)
            ->with(['user', 'seat.row.block'])
            ->get();
        
        $csv = "ID,User Name,Email,Block,Row,Seat,Booked At\n";
        
        foreach ($bookings as $booking) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $booking->id,
                $booking->user->name,
                $booking->user->email,
                $booking->seat->row->block->name ?? 'N/A',
                $booking->seat->row->name ?? 'N/A',
                $booking->seat->label ?? 'N/A',
                $booking->created_at->format('Y-m-d H:i:s')
            );
        }
        
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="bookings-' . $event->id . '.csv"');
    }
}