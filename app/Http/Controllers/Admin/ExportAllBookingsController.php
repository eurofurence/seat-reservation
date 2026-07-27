<?php

namespace App\Http\Controllers\Admin;

use App\Booking\BookingCsvExporter;
use App\Http\Controllers\Controller;
use App\Models\Booking;

class ExportAllBookingsController extends Controller
{
    public function __invoke(BookingCsvExporter $exporter)
    {
        $bookings = Booking::select(['id', 'event_id', 'user_id', 'seat_id', 'name', 'comment', 'picked_up_at', 'created_at'])
            ->with(['event:id,name,room_id', 'event.room:id,name', 'user:id,name'])
            ->withSeatDetails()
            ->get();

        return response($exporter->csv($bookings))
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="bookings-all-events-'.date('Y-m-d').'.csv"');
    }
}
