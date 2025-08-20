<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Room;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

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

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'title' => 'Dashboard',
            'breadcrumbs' => []
        ]);
    }
}