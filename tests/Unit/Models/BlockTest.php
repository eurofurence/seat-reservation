<?php

namespace Tests\Unit\Models;

use App\Models\Block;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function seating_scope_filters_seating_blocks()
    {
        $room = Room::factory()->create();

        // Create different types of blocks
        $seatingBlock1 = Block::factory()->seating()->create(['room_id' => $room->id]);
        $seatingBlock2 = Block::factory()->seating()->create(['room_id' => $room->id]);
        $stageBlock = Block::factory()->stage()->create(['room_id' => $room->id]);

        // Test seating scope
        $seatingBlocks = Block::seating()->get();

        $this->assertCount(2, $seatingBlocks);
        $this->assertTrue($seatingBlocks->contains($seatingBlock1));
        $this->assertTrue($seatingBlocks->contains($seatingBlock2));
        $this->assertFalse($seatingBlocks->contains($stageBlock));
    }

    /** @test */
    public function stage_scope_filters_stage_blocks()
    {
        $room = Room::factory()->create();

        // Create different types of blocks
        $seatingBlock = Block::factory()->seating()->create(['room_id' => $room->id]);
        $stageBlock1 = Block::factory()->stage()->create(['room_id' => $room->id]);
        $stageBlock2 = Block::factory()->stage()->create(['room_id' => $room->id]);

        // Test stage scope
        $stageBlocks = Block::stage()->get();

        $this->assertCount(2, $stageBlocks);
        $this->assertTrue($stageBlocks->contains($stageBlock1));
        $this->assertTrue($stageBlocks->contains($stageBlock2));
        $this->assertFalse($stageBlocks->contains($seatingBlock));
    }

    /** @test */
    public function block_has_room_relationship()
    {
        $room = Room::factory()->create(['name' => 'Test Room']);
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);

        $this->assertEquals('Test Room', $block->room->name);
    }

    /** @test */
    public function block_has_rows_relationship_ordered_by_order()
    {
        $room = Room::factory()->create();
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);

        // Create rows in random order but set specific order values
        $row3 = \App\Models\Row::factory()->create(['block_id' => $block->id, 'order' => 3, 'name' => 'Row C']);
        $row1 = \App\Models\Row::factory()->create(['block_id' => $block->id, 'order' => 1, 'name' => 'Row A']);
        $row2 = \App\Models\Row::factory()->create(['block_id' => $block->id, 'order' => 2, 'name' => 'Row B']);

        $rows = $block->rows;

        $this->assertCount(3, $rows);
        // Should be ordered by 'order' field
        $this->assertEquals('Row A', $rows[0]->name);
        $this->assertEquals('Row B', $rows[1]->name);
        $this->assertEquals('Row C', $rows[2]->name);
    }
}
