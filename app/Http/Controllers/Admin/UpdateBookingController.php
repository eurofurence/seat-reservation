<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class UpdateBookingController extends Controller
{
    public function __invoke(Request $request, $id, $bookingId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::where('id', $bookingId)
            ->where('event_id', $id)
            ->firstOrFail();

        $booking->update([
            'name' => $request->name,
            'comment' => $request->comment,
        ]);

        return back()->with('success', 'Booking updated successfully');
    }
}
