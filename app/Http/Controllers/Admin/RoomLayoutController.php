<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomLayoutController extends Controller
{
    public function edit(Room $room)
    {
        // Load only block positioning data with aggregated counts - no individual seat data
        $blocks = $room->blocks()
            ->select('blocks.*')
            ->selectRaw('COUNT(DISTINCT rows.id) as rows_count')
            ->selectRaw('COUNT(seats.id) as total_seats')
            ->leftJoin('rows', 'blocks.id', '=', 'rows.block_id')
            ->leftJoin('seats', 'rows.id', '=', 'seats.row_id')
            ->groupBy('blocks.id')
            ->orderBy('blocks.sort')
            ->get();
        
        return Inertia::render('Admin/RoomLayout/Edit', [
            'room' => $room,
            'blocks' => $blocks,
        ]);
    }

    public function update(Request $request, Room $room)
    {
        $request->validate([
            'stage_x' => 'required|integer|min:0',
            'stage_y' => 'required|integer|min:0',
            'blocks' => 'required|array',
            'blocks.*.id' => 'required|exists:blocks,id',
            'blocks.*.position_x' => 'required|integer|min:-1',
            'blocks.*.position_y' => 'required|integer|min:-1', 
            'blocks.*.rotation' => 'required|integer|in:0,90,180,270',
        ]);

        // Update stage position
        $room->update([
            'stage_x' => $request->stage_x,
            'stage_y' => $request->stage_y,
        ]);

        // Update block positions
        foreach ($request->blocks as $blockData) {
            $room->blocks()
                ->where('id', $blockData['id'])
                ->update([
                    'position_x' => $blockData['position_x'],
                    'position_y' => $blockData['position_y'],
                    'rotation' => $blockData['rotation'],
                ]);
        }

        return redirect()->back()->with('success', 'Room layout updated successfully!');
    }
}