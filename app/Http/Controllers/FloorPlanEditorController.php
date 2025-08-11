<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FloorPlanEditorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Room $room)
    {
        // Only allow admin users to access the floor plan editor
        if (!Auth::user()->is_admin) {
            abort(403, 'Access denied. Admin privileges required.');
        }

        // Super optimized: Use withCount to get seat counts without loading seats
        $room->load([
            'blocks:id,room_id,name,position_x,position_y,rotation,z_index,grid_column,grid_row,grid_column_span,grid_row_span',
            'blocks.rows:id,block_id,name'
        ]);
        
        $room->blocks->load(['rows' => function ($query) {
            $query->withCount('seats');
        }]);

        return Inertia::render('Admin/FloorPlanEditor', [
            'room' => $room->only(['id', 'name', 'layout_config']),
            'blocks' => $room->blocks->map(function ($block) {
                return [
                    'id' => $block->id,
                    'name' => $block->name,
                    'position_x' => $block->position_x ?? 0,
                    'position_y' => $block->position_y ?? 0,
                    'rotation' => $block->rotation ?? 0,
                    'z_index' => $block->z_index ?? 1,
                    'grid_column' => $block->grid_column ?? 1,
                    'grid_row' => $block->grid_row ?? 1,
                    'grid_column_span' => $block->grid_column_span ?? 1,
                    'grid_row_span' => $block->grid_row_span ?? 1,
                    'rows' => $block->rows->map(function ($row) {
                        $seatCount = $row->seats_count ?? 0;
                        return [
                            'id' => $row->id,
                            'name' => $row->name,
                            'seat_count' => $seatCount,
                            // Don't load seats at all - let frontend generate them dynamically
                            'seats' => []
                        ];
                    })->toArray()
                ];
            })->toArray(),
            'layout_config' => $room->layout_config ?? [
                'grid_columns' => 12,
                'grid_rows' => 8,
                'gap_size' => 48,
                'show_grid_lines' => true,
                'stage' => null
            ]
        ]);
    }

    public function update(Request $request, Room $room)
    {
        if (!Auth::user()->is_admin) {
            abort(403, 'Access denied. Admin privileges required.');
        }
        
        // Log incoming data for debugging
        \Log::info('Floor plan update request:', [
            'layout_config' => $request->layout_config,
            'blocks_count' => count($request->blocks ?? [])
        ]);
        
        $request->validate([
            'layout_config' => 'required|array',
            'blocks' => 'required|array',
            'blocks.*.id' => 'required|exists:blocks,id',
            'blocks.*.grid_column' => 'required|integer|min:1',
            'blocks.*.grid_row' => 'required|integer|min:1',
            'blocks.*.grid_column_span' => 'required|integer|min:1',
            'blocks.*.grid_row_span' => 'required|integer|min:1',
        ]);

        // Update room layout config
        $room->update([
            'layout_config' => $request->layout_config
        ]);
        
        // Log the updated room config
        \Log::info('Room updated with layout_config:', $room->fresh()->layout_config);

        // Update block grid positions
        foreach ($request->blocks as $blockData) {
            Block::where('id', $blockData['id'])
                ->update([
                    'grid_column' => $blockData['grid_column'],
                    'grid_row' => $blockData['grid_row'],
                    'grid_column_span' => $blockData['grid_column_span'],
                    'grid_row_span' => $blockData['grid_row_span'],
                ]);
        }

        return response()->json(['message' => 'Floor plan updated successfully']);
    }

    public function createBlock(Request $request, Room $room)
    {
        if (!Auth::user()->is_admin) {
            abort(403, 'Access denied. Admin privileges required.');
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'grid_column' => 'required|integer|min:1',
            'grid_row' => 'required|integer|min:1',
            'grid_column_span' => 'required|integer|min:1',
            'grid_row_span' => 'required|integer|min:1',
            'rows' => 'required|array|min:1',
            'rows.*.name' => 'required|string',
            'rows.*.seat_count' => 'required|integer|min:1|max:50',
        ]);

        $block = $room->blocks()->create([
            'name' => $request->name,
            'grid_column' => $request->grid_column,
            'grid_row' => $request->grid_row,
            'grid_column_span' => $request->grid_column_span,
            'grid_row_span' => $request->grid_row_span,
            'position_x' => 0, // Keep for backwards compatibility
            'position_y' => 0,
            'rotation' => 0,
            'z_index' => 1,
        ]);

        foreach ($request->rows as $rowData) {
            $row = $block->rows()->create([
                'name' => $rowData['name'],
            ]);

            // Create seats for this row
            for ($i = 1; $i <= $rowData['seat_count']; $i++) {
                $row->seats()->create([
                    'name' => (string) $i, // 1, 2, 3, etc.
                ]);
            }
        }

        return response()->json(['message' => 'Block created successfully']);
    }

    public function updateBlock(Request $request, Room $room, Block $block)
    {
        if (!Auth::user()->is_admin) {
            abort(403, 'Access denied. Admin privileges required.');
        }

        $request->validate([
            'grid_column' => 'sometimes|integer|min:1',
            'grid_row' => 'sometimes|integer|min:1',
            'grid_column_span' => 'sometimes|integer|min:1',
            'grid_row_span' => 'sometimes|integer|min:1',
            'rotation' => 'sometimes|integer|min:0|max:359',
            'rows' => 'sometimes|array',
            'rows.*.name' => 'required_with:rows|string',
            'rows.*.seat_count' => 'required_with:rows|integer|min:1|max:50',
        ]);

        // Update block grid position
        $block->update($request->only([
            'grid_column', 'grid_row', 'grid_column_span', 'grid_row_span', 'rotation'
        ]));

        // Update rows if provided
        if ($request->has('rows')) {
            // Delete existing rows and seats
            $block->rows()->each(function ($row) {
                $row->seats()->delete();
                $row->delete();
            });

            // Create new rows
            foreach ($request->rows as $rowData) {
                $row = $block->rows()->create([
                    'name' => $rowData['name'],
                ]);

                // Create seats for this row
                for ($i = 1; $i <= $rowData['seat_count']; $i++) {
                    $row->seats()->create([
                        'name' => (string) $i, // 1, 2, 3, etc.
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Block updated successfully']);
    }

    public function deleteBlock(Block $block)
    {
        if (!Auth::user()->is_admin) {
            abort(403, 'Access denied. Admin privileges required.');
        }
        $block->delete();
        return response()->json(['message' => 'Block deleted successfully']);
    }
}
