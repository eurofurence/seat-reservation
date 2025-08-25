<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index()
    {
        // Load events with only essential data for the listing page
        $events = Event::with('room:id,name')
            ->where('reservation_ends_at', '>', now())
            ->where('starts_at', '>', now())
            ->select('id', 'room_id', 'name', 'starts_at', 'reservation_ends_at', 'tickets', 'max_tickets')
            ->get();

        // Manually add tickets_left without triggering the heavy relationship loading
        $events->each(function ($event) {
            $maxTickets = $event->max_tickets ?? $event->tickets ?? 0;
            $bookedTickets = $event->bookings()->count();
            $event->tickets_left = max(0, $maxTickets - $bookedTickets);
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
