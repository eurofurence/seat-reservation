<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoomLayoutController extends Controller
{
    public function edit(Room $room)
    {
        // Load block positioning data with row seat counts
        $blocks = $room->blocks()
            ->select('blocks.*')
            ->selectRaw('COUNT(DISTINCT rows.id) as rows_count')
            ->selectRaw('COUNT(seats.id) as total_seats')
            ->with(['rows' => function ($query) {
                $query->select('id', 'block_id', 'name', 'order', 'seats_count', 'custom_seat_count', 'alignment')
                    ->orderBy('order');
            }])
            ->leftJoin('rows', 'blocks.id', '=', 'rows.block_id')
            ->leftJoin('seats', 'rows.id', '=', 'seats.row_id')
            ->groupBy('blocks.id')
            ->orderBy('blocks.order')
            ->get();

        // Load stage blocks
        $stageBlocks = $room->stageBlocks()->get();

        $markerBlocks = $room->markerBlocks()->get();

        // If no stage blocks exist but old stage_x/stage_y exist, migrate them
        if ($stageBlocks->isEmpty() && ($room->stage_x !== null || $room->stage_y !== null)) {
            $stageBlocks = collect([
                $room->allBlocks()->create([
                    'name' => 'Stage',
                    'type' => 'stage',
                    'position_x' => $room->stage_x ?? 0,
                    'position_y' => $room->stage_y ?? 0,
                    'rotation' => 0,
                    'order' => 0,
                ]),
            ]);
        }

        // Bookings tied to this room (via its events). Editing the layout deletes rows and
        // seats, which cascades to these bookings, so the UI warns/guards when > 0.
        //
        // TEMPORARY: this destructive behaviour is a stopgap. Until layout edits can
        // preserve/re-map existing bookings, we surface a warning banner and a typed
        // confirmation in the editor. Remove the warning/guard once bookings survive edits.
        $bookingsCount = Booking::whereHas('event', function ($query) use ($room) {
            $query->where('room_id', $room->id);
        })->count();

        return Inertia::render('Admin/RoomLayout/Edit', [
            'room' => $room->only(['id', 'name']),
            'bookingsCount' => $bookingsCount,
            'blocks' => $blocks,
            'stageBlocks' => $stageBlocks,
            'markerBlocks' => $markerBlocks,
            'blockIdMap' => session('blockIdMap', []),
            'title' => 'Floor Plan Editor',
            'breadcrumbs' => [
                ['title' => 'Rooms', 'url' => route('admin.rooms.index')],
                ['title' => $room->name, 'url' => null],
                ['title' => 'Floor Plan Editor', 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, Room $room)
    {
        $roomBlockIds = $room->blocks()->pluck('id')->all();

        $request->validate([
            'stageBlocks' => 'sometimes|array',
            'stageBlocks.*.id' => ['nullable', Rule::in($room->stageBlocks()->pluck('id')->all())],
            'stageBlocks.*.name' => 'required|string|max:255',
            'stageBlocks.*.position_x' => 'required|integer|min:-1',
            'stageBlocks.*.position_y' => 'required|integer|min:-1',
            'markerBlocks' => 'sometimes|array',
            'markerBlocks.*.id' => ['nullable', Rule::in($room->markerBlocks()->pluck('id')->all())],
            'markerBlocks.*.type' => 'required|string|in:entrance,comment',
            'markerBlocks.*.name' => 'required|string|max:255',
            'markerBlocks.*.position_x' => 'required|integer|min:-1',
            'markerBlocks.*.position_y' => 'required|integer|min:-1',
            'markerBlocks.*.rotation' => 'nullable|integer|in:0,90,180,270',
            'blocks' => 'required|array',
            'blocks.*.id' => [
                'required',
                function (string $attribute, $value, $fail) use ($roomBlockIds) {
                    $isTempId = is_string($value) && str_starts_with($value, 'temp-');
                    $isExistingRoomBlock = is_numeric($value) && in_array((int) $value, $roomBlockIds, true);

                    if (! $isTempId && ! $isExistingRoomBlock) {
                        $fail('The selected block id is invalid for this room.');
                    }
                },
            ],
            'blocks.*.name' => 'required|string|max:255',
            'blocks.*.position_x' => 'required|integer|min:-1',
            'blocks.*.position_y' => 'required|integer|min:-1',
            'blocks.*.rotation' => 'required|integer|in:0,90,180,270',
            'blocks.*.rowsData' => 'nullable|array',
            'blocks.*.rowsData.*.rowNumber' => 'integer|min:1|max:50',
            'blocks.*.rowsData.*.seatCount' => 'integer|min:1|max:100',
            'blocks.*.rowsData.*.isCustom' => 'nullable|boolean',
            'blocks.*.rowsData.*.alignment' => 'nullable|string|in:left,center,right',
        ]);

        $blockIdMap = [];

        DB::transaction(function () use ($request, $room, &$blockIdMap) {
            $this->reconcileBlocks($room, $room->stageBlocks(), $request->stageBlocks ?? [], function ($data, $index) {
                return [
                    'name' => $data['name'],
                    'type' => 'stage',
                    'position_x' => $data['position_x'],
                    'position_y' => $data['position_y'],
                    'rotation' => 0,
                    'order' => $index,
                ];
            });

            $this->reconcileBlocks($room, $room->markerBlocks(), $request->markerBlocks ?? [], function ($data, $index) {
                return [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'position_x' => $data['position_x'],
                    'position_y' => $data['position_y'],
                    'rotation' => $data['rotation'] ?? 0,
                    'order' => $index,
                ];
            });

            $nextBlockOrder = ($room->blocks()->max('order') ?? -1) + 1;

            // Update blocks
            foreach ($request->blocks as $blockData) {
                $isTempId = is_string($blockData['id']) && str_starts_with($blockData['id'], 'temp-');

                if ($isTempId) {
                    $block = $room->allBlocks()->create([
                        'name' => $blockData['name'],
                        'type' => 'seating',
                        'position_x' => $blockData['position_x'],
                        'position_y' => $blockData['position_y'],
                        'rotation' => $blockData['rotation'],
                        'order' => $nextBlockOrder++,
                    ]);
                    $blockIdMap[$blockData['id']] = $block->id;
                } else {
                    $block = $room->blocks()->where('id', $blockData['id'])->first();
                }

                if ($block) {
                    // Update block position, rotation, and name
                    $block->update([
                        'name' => $blockData['name'],
                        'position_x' => $blockData['position_x'],
                        'position_y' => $blockData['position_y'],
                        'rotation' => $blockData['rotation'],
                    ]);

                    // If rowsData is provided, update the block structure
                    if (isset($blockData['rowsData']) && is_array($blockData['rowsData'])) {
                        // Delete existing rows (this will cascade to seats)
                        $block->rows()->delete();

                        // Create new rows with specified seat counts and custom seat counts
                        foreach ($blockData['rowsData'] as $rowData) {
                            $customSeatCount = isset($rowData['isCustom']) && $rowData['isCustom'] ? $rowData['seatCount'] : null;
                            $alignment = $rowData['alignment'] ?? 'center';

                            $row = $block->rows()->create([
                                'name' => "Row {$rowData['rowNumber']}",
                                'order' => $rowData['rowNumber'],
                                'seats_count' => $rowData['seatCount'],
                                'custom_seat_count' => $customSeatCount,
                                'alignment' => $alignment,
                            ]);

                            // Create seats for this row
                            for ($seatIndex = 1; $seatIndex <= $rowData['seatCount']; $seatIndex++) {
                                $seatLabel = $this->numberToLetter($seatIndex);

                                $row->seats()->create([
                                    'label' => $seatLabel,
                                    'number' => $seatIndex,
                                ]);
                            }
                        }
                    }
                }
            }
        });

        return back()
            ->with('success', 'Room layout updated successfully!')
            ->with('blockIdMap', $blockIdMap);
    }

    public function createBlock(Request $request, Room $room)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Get the next sort order
        $nextSort = $room->blocks()->max('order') + 1;

        $block = $room->allBlocks()->create([
            'name' => $request->name,
            'type' => 'seating',
            'position_x' => 0,  // Default position 0,0
            'position_y' => 0,
            'rotation' => 0,
            'order' => $nextSort,
        ]);

        return back()->with('success', 'Block created successfully!');
    }

    public function deleteBlock(Room $room, $blockId)
    {
        $block = $room->blocks()->where('id', $blockId)->first();

        if (! $block) {
            return back()->with('error', 'Block not found.');
        }

        // Delete the block (this will cascade to rows and seats)
        $block->delete();

        return back()->with('success', 'Block deleted successfully!');
    }

    /**
     * Delete blocks in $scope absent from $submitted, then update-or-create the rest.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\HasMany  $scope
     * @param  array<int,array<string,mixed>>  $submitted
     */
    private function reconcileBlocks(Room $room, $scope, array $submitted, callable $attributes): void
    {
        $submittedIds = collect($submitted)->pluck('id')->filter()->all();
        $toDelete = array_diff($scope->clone()->pluck('id')->all(), $submittedIds);

        if (! empty($toDelete)) {
            $scope->clone()->whereIn('id', $toDelete)->delete();
        }

        foreach ($submitted as $index => $data) {
            $values = $attributes($data, $index);

            if ($data['id']) {
                $scope->clone()->where('id', $data['id'])->update($values);
            } else {
                $room->allBlocks()->create($values);
            }
        }
    }

    private function numberToLetter(int $number): string
    {
        $result = '';
        while ($number > 0) {
            $number--; // Make it 0-based
            $result = chr(65 + ($number % 26)).$result;
            $number = intval($number / 26);
        }

        return $result;
    }
}
