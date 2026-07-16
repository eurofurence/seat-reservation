<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class TogglePickupController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'picked_up' => 'required|boolean',
        ]);

        $booking = Booking::where('id', $request->booking_id)
            ->where('event_id', $id)
            ->firstOrFail();

        $booking->picked_up_at = $request->picked_up ? now() : null;
        $booking->save();

        return back()->with(
            'success',
            $request->picked_up ? 'Marked as picked up' : 'Marked as not picked up'
        );
    }
}
