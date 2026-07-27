<?php

namespace App\Booking;

use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;

class SeatAssigner
{
    // ponytail: cached per room for one assignment pass, never invalidated - safe since
    // nothing mutates seats mid-pass.
    /** @var array<int, \Illuminate\Support\Collection<int, Block>> */
    private array $blocksCache = [];

    /** @var array<int, \Illuminate\Support\Collection<int, Block>> */
    private array $stageBlocksCache = [];

    private function blocksFor(Room $room): \Illuminate\Support\Collection
    {
        return $this->blocksCache[$room->id] ??= $room->blocks()->with('rows.seats')->get();
    }

    private function stageBlocksFor(Room $room): \Illuminate\Support\Collection
    {
        return $this->stageBlocksCache[$room->id] ??= $room->stageBlocks()->get()->filter(
            fn (Block $stage) => $stage->position_x !== null && $stage->position_y !== null
        );
    }

    /** All bookable seat ids, ordered by block, then row, then seat number. */
    public function orderedRoomSeatIds(Room $room): array
    {
        return Seat::whereHas('row.block', fn ($q) => $q->seating()->where('room_id', $room->id))
            ->with('row.block')
            ->get()
            ->sortBy([
                ['row.block.order', 'asc'],
                ['row.order', 'asc'],
                ['number', 'asc'],
            ])
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * Seat ids grouped per block so a contiguous-run search never spans two blocks.
     * Each block's seats are stage-aware ordered (see orderedSeatIdsForBlock()).
     */
    public function orderedRoomSeatIdsByBlock(Room $room, array $unavailable = []): array
    {
        return $this->blocksFor($room)
            ->map(fn (Block $block) => $this->orderedSeatIdsForBlock($room, $block, $unavailable))
            ->values()
            ->all();
    }

    /**
     * Seats grouped depth-first across blocks: index 0 = every block's front row (block
     * order, $preferBlockName floated first), index 1 = every block's second row, etc, so
     * row 1 fills everywhere before anyone's row 2 does. Each row is its own group so a run
     * never spans two blocks (see orderedRoomSeatIdsByBlock()).
     */
    public function orderedRoomSeatIdsByRowDepth(Room $room, array $unavailable = [], ?string $preferBlockName = null): array
    {
        $blocks = $this->blocksFor($room);

        if ($preferBlockName !== null && $preferBlockName !== '') {
            $preferredBlock = $this->findBlockByName($room, $preferBlockName);
            if ($preferredBlock !== null) {
                $blocks = $blocks->sortBy(
                    fn (Block $block) => $block->id === $preferredBlock->id ? 0 : 1
                )->values();
            }
        }

        $maxDepth = $blocks->max(fn (Block $block) => $block->rows->count()) ?? 0;
        $groups = [];

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            foreach ($blocks as $block) {
                $row = $block->rows->get($depth);
                if ($row === null) {
                    continue;
                }

                $rowSeatIds = $row->seats->pluck('id')->values()->all();
                $groups[] = $this->orderRowSeatIds($rowSeatIds, $this->blockSide($room, $block), $unavailable, isFirstRow: $depth === 0);
            }
        }

        return array_values(array_filter($groups, fn (array $ids) => ! empty($ids)));
    }

    /**
     * Stage-aware ordered seat ids for the block named $blockName (see findBlockByName(),
     * orderedSeatIdsForBlock()). $nearRowName offers rows nearest-first to that row instead
     * of front-to-back. Empty array if no such block exists.
     */
    public function orderedBlockSeatIds(Room $room, string $blockName, array $unavailable = [], ?string $nearRowName = null): array
    {
        $block = $this->findBlockByName($room, $blockName);

        if ($block === null) {
            return [];
        }

        return $this->orderedSeatIdsForBlock($room, $block, $unavailable, $nearRowName);
    }

    /** Like orderedBlockSeatIds(), grouped per row instead of flattened, so a caller can try each row whole before splitting a group across rows. */
    public function orderedBlockSeatIdsByRow(Room $room, string $blockName, array $unavailable = [], ?string $nearRowName = null): array
    {
        $block = $this->findBlockByName($room, $blockName);

        if ($block === null) {
            return [];
        }

        $side = $this->blockSide($room, $block);
        $firstRowId = $block->rows->first()?->id;

        return $this->orderRowsNearest($block->rows, $nearRowName)
            ->map(function (Row $row) use ($side, $unavailable, $firstRowId) {
                $rowSeatIds = $row->seats->pluck('id')->values()->all();

                return $this->orderRowSeatIds($rowSeatIds, $side, $unavailable, isFirstRow: $row->id === $firstRowId);
            })
            ->filter(fn (array $ids) => ! empty($ids))
            ->values()
            ->all();
    }

    /** Stage-aware ordered seat ids for one row in one block. Empty array if no such block/row pair exists. */
    public function orderedRowSeatIds(Room $room, string $blockName, string $rowName, array $unavailable = []): array
    {
        $block = $this->findBlockByName($room, $blockName);

        if ($block === null) {
            return [];
        }

        $rowName = strtolower(trim($rowName));
        $row = $block->rows->first(fn ($row) => strtolower(trim($row->name)) === $rowName);

        if ($row === null) {
            return [];
        }

        $seatIds = $row->seats->pluck('id')->values()->all();

        return $this->orderRowSeatIds($seatIds, $this->blockSide($room, $block), $unavailable, isFirstRow: false);
    }

    /**
     * Stage-aware seat order for one block: left of stage fills right-to-left (aisle side
     * first), right fills left-to-right, center fills from whichever edge has fewer taken
     * seats (keeps one guest's seats contiguous). No stage block -> plain ascending order.
     */
    private function orderedSeatIdsForBlock(Room $room, Block $block, array $unavailable, ?string $nearRowName = null): array
    {
        $side = $this->blockSide($room, $block);
        $firstRowId = $block->rows->first()?->id;
        $rows = $this->orderRowsNearest($block->rows, $nearRowName);
        $seatIds = [];

        foreach ($rows as $row) {
            $rowSeatIds = $row->seats->pluck('id')->values()->all();
            $seatIds = array_merge($seatIds, $this->orderRowSeatIds($rowSeatIds, $side, $unavailable, isFirstRow: $row->id === $firstRowId));
        }

        return $seatIds;
    }

    /** Rows front-to-back, unless $nearRowName names a real row - then nearest-first, ties toward the back. */
    private function orderRowsNearest(\Illuminate\Support\Collection $rows, ?string $nearRowName): \Illuminate\Support\Collection
    {
        $rows = $rows->values();

        if ($nearRowName === null || $nearRowName === '') {
            return $rows;
        }

        $nearRowName = strtolower(trim($nearRowName));
        $preferredIndex = $rows->search(fn ($row) => strtolower(trim($row->name)) === $nearRowName);

        if ($preferredIndex === false) {
            return $rows;
        }

        return $rows
            ->sortBy(fn ($row, $index) => [abs($index - $preferredIndex), -$index])
            ->values();
    }

    /** Applies orderedSeatIdsForBlock()'s direction rule to one row's ascending seat ids. */
    private function orderRowSeatIds(array $ascendingSeatIds, string $side, array $unavailable, bool $isFirstRow): array
    {
        if ($side === 'left') {
            return array_reverse($ascendingSeatIds);
        }

        if ($side === 'center' && ! $isFirstRow) {
            $half = (int) ceil(count($ascendingSeatIds) / 2);
            $unavailableFlip = array_flip($unavailable);
            $firstHalfTaken = count(array_intersect_key(array_flip(array_slice($ascendingSeatIds, 0, $half)), $unavailableFlip));
            $secondHalfTaken = count(array_intersect_key(array_flip(array_slice($ascendingSeatIds, $half)), $unavailableFlip));

            if ($secondHalfTaken < $firstHalfTaken) {
                return array_reverse($ascendingSeatIds);
            }
        }

        return $ascendingSeatIds;
    }

    /** Nearest stage block to $block by straight-line distance, null if neither has coordinates or the room has no stage block. */
    private function nearestStageBlock(Room $room, Block $block): ?Block
    {
        if ($block->position_x === null || $block->position_y === null) {
            return null;
        }

        return $this->stageBlocksFor($room)->sortBy(fn (Block $stage) => sqrt(
            ($stage->position_x - $block->position_x) ** 2 + ($stage->position_y - $block->position_y) ** 2
        ))->first();
    }

    /** 'left'/'right'/'center' relative to the nearest stage block's column. Defaults to 'right' with no stage/coordinates. */
    private function blockSide(Room $room, Block $block): string
    {
        $nearestStage = $this->nearestStageBlock($room, $block);

        if ($nearestStage === null) {
            return 'right';
        }

        if ($block->position_x < $nearestStage->position_x) {
            return 'left';
        }

        if ($block->position_x > $nearestStage->position_x) {
            return 'right';
        }

        return 'center';
    }

    /** Distance from $block to its nearest stage block, PHP_FLOAT_MAX with no stage/coordinates - ranks same-name block matches by closeness to the stage. */
    private function distanceToStage(Room $room, Block $block): float
    {
        $nearestStage = $this->nearestStageBlock($room, $block);

        if ($nearestStage === null) {
            return PHP_FLOAT_MAX;
        }

        return sqrt(($nearestStage->position_x - $block->position_x) ** 2 + ($nearestStage->position_y - $block->position_y) ** 2);
    }

    /** Whether $blockName resolves to a real block in this room (see findBlockByName()). */
    public function blockExists(Room $room, string $blockName): bool
    {
        return $this->findBlockByName($room, $blockName) !== null;
    }

    /**
     * Finds a block by name (case-insensitive, trimmed). Falls back to a name that merely
     * contains $blockName (e.g. "center" matches "Front Center"/"Back Center"), preferring
     * whichever match is closest to the stage. Null if nothing matches or $blockName is blank.
     */
    private function findBlockByName(Room $room, string $blockName): ?Block
    {
        $blockName = strtolower(trim($blockName));

        if ($blockName === '') {
            return null;
        }

        $blocks = $this->blocksFor($room);

        $exact = $blocks->first(fn (Block $block) => strtolower(trim($block->name)) === $blockName);

        if ($exact !== null) {
            return $exact;
        }

        return $blocks
            ->filter(fn (Block $block) => str_contains(strtolower(trim($block->name)), $blockName))
            ->sortBy(fn (Block $block) => $this->distanceToStage($room, $block))
            ->first();
    }

    /** First run of $quantity consecutive seats (per $allSeatIdsInOrder) all absent from $unavailableSeatIds. May cross a row boundary. Null if none exists. */
    public function findContiguousRun(array $allSeatIdsInOrder, array $unavailableSeatIds, int $quantity): ?array
    {
        if ($quantity <= 0) {
            return null;
        }

        $unavailable = array_flip($unavailableSeatIds);
        $streak = [];

        foreach ($allSeatIdsInOrder as $seatId) {
            if (isset($unavailable[$seatId])) {
                $streak = [];

                continue;
            }

            $streak[] = $seatId;

            if (count($streak) === $quantity) {
                return $streak;
            }
        }

        return null;
    }

    /** findContiguousRun() per block group, so a run never spans two blocks. */
    public function findContiguousRoomRun(array $seatIdsByBlock, array $unavailableSeatIds, int $quantity): ?array
    {
        foreach ($seatIdsByBlock as $blockSeatIds) {
            $run = $this->findContiguousRun($blockSeatIds, $unavailableSeatIds, $quantity);
            if ($run !== null) {
                return $run;
            }
        }

        return null;
    }

    /** Moves every guest whose seats collide with $conflictSeatIds onto a fresh contiguous run. Returns [reassignedGuests, affectedNames], or null if the room can't fit one of them. */
    public function reassignConflictedGuests(Event $event, array $guests, array $conflictSeatIds): ?array
    {
        $affectedIndexes = [];
        foreach ($guests as $index => $guest) {
            if (array_intersect($guest['seat_ids'], $conflictSeatIds)) {
                $affectedIndexes[] = $index;
            }
        }

        $unavailable = array_merge(
            Booking::where('event_id', $event->id)->pluck('seat_id')->all(),
            collect($guests)->except($affectedIndexes)->pluck('seat_ids')->flatten()->all()
        );

        foreach ($affectedIndexes as $index) {
            $quantity = count($guests[$index]['seat_ids']);
            $seatIdsByBlock = $this->orderedRoomSeatIdsByBlock($event->room, $unavailable);
            $run = $this->findContiguousRoomRun($seatIdsByBlock, $unavailable, $quantity);

            if ($run === null) {
                return null;
            }

            $guests[$index]['seat_ids'] = $run;
            // Whole-room scan - overwrite any stale row/block preview badge.
            $guests[$index]['fallback_level_used'] = 'room';
            $unavailable = array_merge($unavailable, $run);
        }

        $names = collect($affectedIndexes)->map(fn ($i) => $guests[$i]['guest_name'])->all();

        return [$guests, $names];
    }
}
