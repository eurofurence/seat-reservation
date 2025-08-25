<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RoomAdminController extends Controller
{
    public function index()
    {
        $rooms = Room::select('id', 'name', 'created_at', 'updated_at')
            ->withCount('blocks')
            ->addSelect([
                'total_seats' => DB::table('seats')
                    ->join('rows', 'seats.row_id', '=', 'rows.id')
                    ->join('blocks', 'rows.block_id', '=', 'blocks.id')
                    ->whereColumn('blocks.room_id', 'rooms.id')
                    ->selectRaw('count(seats.id)'),
            ])
            ->get();

        return Inertia::render('Admin/RoomIndex', [
            'rooms' => $rooms,
            'title' => 'Rooms',
            'breadcrumbs' => [],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Room::create([
            'name' => $request->name,
            'stage_x' => 0,
            'stage_y' => 0,
        ]);

        return redirect()->route('admin.rooms.index');
    }

    public function edit($id)
    {
        $room = Room::findOrFail($id);

        return Inertia::render('Admin/RoomEdit', [
            'room' => $room,
            'title' => 'Edit Room - '.$room->name,
            'breadcrumbs' => [
                ['title' => 'Rooms', 'url' => route('admin.rooms.index')],
                ['title' => $room->name, 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $room = Room::findOrFail($id);
        $room->update([
            'name' => $request->name,
        ]);

        return redirect()->route('admin.rooms.index');
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $roomName = $room->name;
        $room->delete();

        return redirect()->route('admin.rooms.index')
            ->with('success', "Room '{$roomName}' has been deleted successfully.");
    }
}
