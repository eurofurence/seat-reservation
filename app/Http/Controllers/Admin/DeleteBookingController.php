<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;

class DeleteBookingController extends Controller
{
    public function __invoke($id, $bookingId)
    {
        $booking = Booking::where('id', $bookingId)
            ->where('event_id', $id)
            ->firstOrFail();

        $booking->delete();

        return back()->with('success', 'Booking deleted successfully');
    }
}
