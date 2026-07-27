<?php

namespace App\Http\Controllers\Admin;

use App\Booking\BookingCsvExporter;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;

class ExportBookingsController extends Controller
{
    public function __invoke($id, BookingCsvExporter $exporter)
    {
        $event = Event::with('room')->findOrFail($id);

        $bookings = Booking::select(['id', 'event_id', 'user_id', 'seat_id', 'name', 'comment', 'picked_up_at', 'created_at'])
            ->where('event_id', $id)
            ->with('user:id,name')
            ->withSeatDetails()
            ->get();

        return response($exporter->csv($bookings, $event))
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="bookings-'.$event->name.'-'.date('Y-m-d').'.csv"');
    }
}
