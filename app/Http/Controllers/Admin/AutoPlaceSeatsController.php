<?php

namespace App\Http\Controllers\Admin;

use App\Booking\ImportProposalBuilder;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class AutoPlaceSeatsController extends Controller
{
    /**
     * Re-run the CSV import's auto-assignment for a single guest on the review screen (the
     * "Auto-place seats" button), so the manual button uses the exact same stage-aware,
     * preference-tiered logic as the initial import instead of a separate client heuristic.
     * $unavailable is the client's live view (booked seats + seats other guests in this
     * batch currently hold) - the assignment is confined to the event's own room regardless.
     */
    public function __invoke(Request $request, $id, ImportProposalBuilder $builder)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'strategy' => 'nullable|string|in:row_preferred,block_preferred,none',
            // row_preferred/block_preferred reach typed SeatAssigner calls that require the
            // location strings, so make them mandatory for those strategies here (the trust
            // boundary) rather than 500ing on a TypeError deeper in the assignment.
            'preferred_block' => 'required_if:strategy,row_preferred,block_preferred|nullable|string|max:255',
            'preferred_row' => 'required_if:strategy,row_preferred|nullable|string|max:255',
            'unavailable' => 'nullable|array',
            'unavailable.*' => 'integer',
        ]);

        $event = Event::with('room')->findOrFail($id);

        $assigned = $builder->assignGroup(
            $event,
            $data['quantity'],
            $data['strategy'] ?? 'none',
            $data['preferred_block'] ?? null,
            $data['preferred_row'] ?? null,
            $data['unavailable'] ?? [],
        );

        if ($assigned === null) {
            return response()->json([
                'error' => 'Not enough free seats remain in this room to place all of this guest\'s seats.',
            ], 422);
        }

        return response()->json($assigned);
    }
}
