<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index()
    {
        // Load events with only essential data for the listing page.
        // Events are shown even before their booking window opens, so staff/users
        // can see what's planned; the seat picker itself blocks booking until open.
        $events = Event::with('room:id,name')
            ->where('reservation_ends_at', '>', now())
            ->where('starts_at', '>', now())
            ->select('id', 'room_id', 'name', 'starts_at', 'reservation_ends_at', 'booking_starts_at', 'tickets', 'max_tickets')
            ->get();

        // tickets_left isn't in $appends (see Event model), so force it into each
        // model's attributes here to include it in the response.
        $events->each(function ($event) {
            $event->tickets_left = $event->tickets_left;
            $event->is_booking_open = $event->isBookingOpen();
        });

        return Inertia::render('Event/IndexEvent', [
            'events' => $events,
        ]);
    }

    public function create() {}

    public function store(Request $request) {}

    public function show(Event $event) {}

    public function edit(Event $event) {}

    public function update(Request $request, Event $event) {}

    public function destroy(Event $event) {}
}
