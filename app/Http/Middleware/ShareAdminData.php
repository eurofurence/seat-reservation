<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Event;
use Carbon\Carbon;

class ShareAdminData
{
    public function handle(Request $request, Closure $next)
    {
        // Share upcoming events with all admin pages for the sidebar
        Inertia::share([
            'events' => function () {
                return Event::select('id', 'name', 'starts_at')
                    ->where('starts_at', '>', Carbon::now())
                    ->orderBy('starts_at')
                    ->limit(10)
                    ->get();
            },
        ]);

        return $next($request);
    }
}