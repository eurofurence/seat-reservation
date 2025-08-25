<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'totalEvents' => Event::count(),
            'upcomingEvents' => Event::where('starts_at', '>', Carbon::now())->count(),
            'totalBookings' => Booking::count(),
            'totalRooms' => Room::count(),
        ];

        // Get recent bookings with event and seat information
        $recentBookings = Booking::with([
            'event:id,name,starts_at',
            'user:id,name',
            'seat:id,row_id,label',
            'seat.row:id,block_id,name',
            'seat.row.block:id,name',
        ])
            ->select('id', 'event_id', 'user_id', 'seat_id', 'name', 'booking_code', 'type', 'picked_up_at', 'created_at')
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentBookings' => $recentBookings,
            'title' => 'Dashboard',
            'breadcrumbs' => [],
        ]);
    }

    public function lookupBookingCode(Request $request)
    {
        $request->validate([
            'booking_code' => 'required|string|size:3',
        ]);

        $bookingCode = strtoupper($request->booking_code);

        // Find the first booking with this code to get the event
        $booking = Booking::where('booking_code', $bookingCode)
            ->with('event')
            ->first();

        if (! $booking) {
            return back()->withErrors([
                'booking_code' => 'No booking found with this code.',
            ]);
        }

        // Redirect to the event page with the booking code as a filter
        return redirect()->route('admin.events.show', [
            'event' => $booking->event_id,
            'bookingcode' => $bookingCode,
        ]);
    }
}
