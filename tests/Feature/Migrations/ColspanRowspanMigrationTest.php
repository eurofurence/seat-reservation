<?php

namespace Tests\Feature\Migrations;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

class ColspanRowspanMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_07_26_113314_add_colspan_and_rowspan_to_blocks_table.php');
    }

    private function callPrivate(object $object, string $method, array $args = [])
    {
        $method = (new ReflectionClass($object))->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    private function insertBlock(int $roomId, array $overrides = []): int
    {
        return DB::table('blocks')->insertGetId(array_merge([
            'room_id' => $roomId,
            'name' => 'A',
            'type' => 'seating',
            'position_x' => 0,
            'position_y' => 0,
            'rotation' => 0,
            'colspan' => 1,
            'rowspan' => 1,
            'order' => 0,
        ], $overrides));
    }

    /** @test */
    public function merge_joins_adjacent_stage_and_comment_blocks_but_leaves_seating_alone(): void
    {
        $room = Room::factory()->create();

        // Two adjacent stage blocks, side by side -> should merge into one with colspan 2.
        $stageId = $this->insertBlock($room->id, ['name' => 'Stage', 'type' => 'stage', 'position_x' => 0, 'position_y' => 0]);
        $this->insertBlock($room->id, ['name' => 'Stage', 'type' => 'stage', 'position_x' => 1, 'position_y' => 0]);

        // Two adjacent comment blocks, stacked -> should merge into one with rowspan 2.
        $commentId = $this->insertBlock($room->id, ['name' => 'Note', 'type' => 'comment', 'position_x' => 5, 'position_y' => 5]);
        $this->insertBlock($room->id, ['name' => 'Note', 'type' => 'comment', 'position_x' => 5, 'position_y' => 6]);

        // A seating block sitting right next to another seating block - merge must never
        // touch seating, so this must stay as two separate, unspanned blocks.
        $seatingId = $this->insertBlock($room->id, ['name' => 'B1', 'type' => 'seating', 'position_x' => 9, 'position_y' => 9]);
        $this->insertBlock($room->id, ['name' => 'B1', 'type' => 'seating', 'position_x' => 10, 'position_y' => 9]);

        $this->callPrivate($this->migration(), 'mergeAdjacentBlocks');

        $this->assertDatabaseHas('blocks', ['id' => $stageId, 'colspan' => 2, 'rowspan' => 1]);
        $this->assertDatabaseHas('blocks', ['id' => $commentId, 'colspan' => 1, 'rowspan' => 2]);
        $this->assertSame(2, DB::table('blocks')->where('id', $seatingId)->orWhere('name', 'B1')->count());
        $this->assertSame(4, DB::table('blocks')->where('room_id', $room->id)->count());
    }

    /** @test */
    public function rollback_splits_stage_and_comment_blocks_but_not_a_spanned_seating_block(): void
    {
        $room = Room::factory()->create();

        $stageId = $this->insertBlock($room->id, [
            'name' => 'Stage', 'type' => 'stage', 'position_x' => 0, 'position_y' => 0, 'colspan' => 2,
        ]);
        $commentId = $this->insertBlock($room->id, [
            'name' => 'Note', 'type' => 'comment', 'position_x' => 5, 'position_y' => 5, 'rowspan' => 2,
        ]);
        $seatingId = $this->insertBlock($room->id, [
            'name' => 'B1', 'type' => 'seating', 'position_x' => 9, 'position_y' => 9, 'colspan' => 2,
        ]);

        $this->callPrivate($this->migration(), 'splitSpannedBlocks');

        $this->assertDatabaseHas('blocks', ['id' => $stageId, 'position_x' => 0, 'position_y' => 0, 'colspan' => 2]);
        $this->assertDatabaseHas('blocks', ['name' => 'Stage', 'type' => 'stage', 'position_x' => 1, 'position_y' => 0]);
        $this->assertDatabaseHas('blocks', ['id' => $commentId, 'position_x' => 5, 'position_y' => 5, 'rowspan' => 2]);
        $this->assertDatabaseHas('blocks', ['name' => 'Note', 'type' => 'comment', 'position_x' => 5, 'position_y' => 6]);

        // Left untouched: splitting would clone an empty seating block next to the real one,
        // duplicating it without any of its actual rows/seats.
        $this->assertDatabaseHas('blocks', ['id' => $seatingId, 'position_x' => 9, 'position_y' => 9, 'colspan' => 2]);
        $this->assertSame(1, DB::table('blocks')->where('name', 'B1')->count());

        $this->assertSame(5, DB::table('blocks')->where('room_id', $room->id)->count());
    }
}
