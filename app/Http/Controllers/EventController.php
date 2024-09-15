<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Seat;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index()
    {
        return Inertia::render('Event/IndexEvent', [
            'events' => Event::with('room')->where('reservation_ends_at', '>', now())->where('starts_at', '>', now())
                ->get()
        ]);
    }

    public function create()
    {
    }

    public function store(Request $request)
    {
    }

    public function show(Event $event)
    {
    }

    public function edit(Event $event)
    {
    }

    public function update(Request $request, Event $event)
    {
    }

    public function destroy(Event $event)
    {
    }
}
