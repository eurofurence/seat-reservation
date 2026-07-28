<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RoomLayoutControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $admin;

    private $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->room = Room::factory()->create();
    }

    /** @test */
    public function admin_can_view_room_layout_editor()
    {
        $this->actingAs($this->admin);

        // Create some blocks and stage blocks
        $seatingBlock = Block::factory()->seating()->create(['room_id' => $this->room->id, 'name' => 'Block A']);
        $row = Row::factory()->create(['block_id' => $seatingBlock->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);

        $stageBlock = Block::factory()->stage()->create(['room_id' => $this->room->id, 'name' => 'Main Stage']);

        $response = $this->get(route('admin.rooms.layout', $this->room->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/RoomLayout/Edit')
            ->where('room.id', $this->room->id)
            ->where('title', 'Floor Plan Editor')
            ->has('blocks', 1)
            ->has('stageBlocks', 1)
            ->where('blocks.0.name', 'Block A')
            ->where('stageBlocks.0.name', 'Main Stage')
        );
    }

    /** @test */
    public function room_layout_migrates_old_stage_position()
    {
        $this->actingAs($this->admin);

        // Create room with old stage_x/stage_y values but no stage blocks
        $this->room->update(['stage_x' => 5, 'stage_y' => 3]);

        $response = $this->get(route('admin.rooms.layout', $this->room->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('stageBlocks', 1)
            ->where('stageBlocks.0.name', 'Stage')
            ->where('stageBlocks.0.position_x', 5)
            ->where('stageBlocks.0.position_y', 3)
        );

        // Verify stage block was created in database
        $this->assertDatabaseHas('blocks', [
            'room_id' => $this->room->id,
            'type' => 'stage',
            'name' => 'Stage',
            'position_x' => 5,
            'position_y' => 3,
        ]);
    }

    /** @test */
    public function admin_can_update_room_layout()
    {
        $this->actingAs($this->admin);

        $seatingBlock = Block::factory()->seating()->create([
            'room_id' => $this->room->id,
            'name' => 'Old Block',
            'position_x' => 0,
            'position_y' => 0,
            'rotation' => 0,
        ]);

        $stageBlock = Block::factory()->stage()->create([
            'room_id' => $this->room->id,
            'name' => 'Old Stage',
            'position_x' => 1,
            'position_y' => 1,
        ]);

        $updateData = [
            'stageBlocks' => [
                [
                    'id' => $stageBlock->id,
                    'name' => 'Main Stage',
                    'position_x' => 5,
                    'position_y' => 3,
                ],
            ],
            'blocks' => [
                [
                    'id' => $seatingBlock->id,
                    'name' => 'VIP Section',
                    'position_x' => 2,
                    'position_y' => 4,
                    'rotation' => 90,
                    'rowsData' => [
                        ['rowNumber' => 1, 'seatCount' => 10, 'isCustom' => false],
                        ['rowNumber' => 2, 'seatCount' => 8, 'isCustom' => true],
                    ],
                ],
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $updateData);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Room layout updated successfully!');

        // Verify stage block update
        $this->assertDatabaseHas('blocks', [
            'id' => $stageBlock->id,
            'name' => 'Main Stage',
            'position_x' => 5,
            'position_y' => 3,
        ]);

        // Verify seating block update
        $this->assertDatabaseHas('blocks', [
            'id' => $seatingBlock->id,
            'name' => 'VIP Section',
            'position_x' => 2,
            'position_y' => 4,
            'rotation' => 90,
        ]);

        // Verify rows and seats were created
        $this->assertDatabaseHas('rows', [
            'block_id' => $seatingBlock->id,
            'name' => 'Row 1',
            'seats_count' => 10,
            'custom_seat_count' => null,
        ]);

        $this->assertDatabaseHas('rows', [
            'block_id' => $seatingBlock->id,
            'name' => 'Row 2',
            'seats_count' => 8,
            'custom_seat_count' => 8,
        ]);

        // Verify seats were created (18 total: 10 + 8)
        $totalSeats = Seat::whereHas('row', function ($query) use ($seatingBlock) {
            $query->where('block_id', $seatingBlock->id);
        })->count();
        $this->assertEquals(18, $totalSeats);
    }

    /** @test */
    public function layout_update_validates_required_fields()
    {
        $this->actingAs($this->admin);
        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                [
                    'id' => $block->id,
                    'name' => '', // Missing name
                    'position_x' => -2, // Invalid position
                    'position_y' => 0,
                    'rotation' => 45, // Invalid rotation
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['blocks.0.name', 'blocks.0.position_x', 'blocks.0.rotation']);
    }

    /** @test */
    public function layout_update_rejects_overlapping_blocks_across_collections()
    {
        $this->actingAs($this->admin);

        $seatingBlock = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $stageBlock = Block::factory()->stage()->create(['room_id' => $this->room->id]);

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'stageBlocks' => [
                ['id' => $stageBlock->id, 'name' => 'Stage', 'position_x' => 2, 'position_y' => 2],
            ],
            'blocks' => [
                [
                    'id' => $seatingBlock->id,
                    'name' => 'Block',
                    'position_x' => 2,
                    'position_y' => 2,
                    'rotation' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['stageBlocks.0.position_x', 'blocks.0.position_x']);
    }

    /** @test */
    public function layout_update_rejects_overlapping_blocks_within_a_collection()
    {
        $this->actingAs($this->admin);

        $blockA = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $blockB = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                ['id' => $blockA->id, 'name' => 'Block A', 'position_x' => 3, 'position_y' => 3, 'rotation' => 0, 'colspan' => 2, 'rowspan' => 2],
                ['id' => $blockB->id, 'name' => 'Block B', 'position_x' => 4, 'position_y' => 4, 'rotation' => 0],
            ],
        ]);

        $response->assertSessionHasErrors(['blocks.0.position_x', 'blocks.1.position_x']);
    }

    /** @test */
    public function layout_update_allows_adjacent_non_overlapping_blocks()
    {
        $this->actingAs($this->admin);

        $blockA = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $blockB = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                ['id' => $blockA->id, 'name' => 'Block A', 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
                ['id' => $blockB->id, 'name' => 'Block B', 'position_x' => 1, 'position_y' => 0, 'rotation' => 0],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
    }

    /** @test */
    public function layout_update_creates_block_from_temp_id_and_returns_mapping()
    {
        $this->actingAs($this->admin);

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                [
                    'id' => 'temp-1',
                    'name' => 'Block',
                    'position_x' => 2,
                    'position_y' => 3,
                    'rotation' => 90,
                    'rowsData' => [
                        ['rowNumber' => 1, 'seatCount' => 5, 'isCustom' => false, 'alignment' => 'center'],
                    ],
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('blocks', [
            'room_id' => $this->room->id,
            'name' => 'Block',
            'type' => 'seating',
            'position_x' => 2,
            'position_y' => 3,
            'rotation' => 90,
        ]);

        $realId = Block::where('room_id', $this->room->id)->where('name', 'Block')->value('id');
        $response->assertSessionHas('blockIdMap', ['temp-1' => $realId]);
    }

    /** @test */
    public function layout_update_returns_id_mapping_for_new_stage_and_marker_blocks()
    {
        $this->actingAs($this->admin);

        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'stageBlocks' => [
                ['id' => 'temp-1', 'name' => 'Stage 1', 'position_x' => 0, 'position_y' => 0],
            ],
            'markerBlocks' => [
                ['id' => 'temp-2', 'type' => 'comment', 'name' => 'Note', 'position_x' => 1, 'position_y' => 1, 'rotation' => 0],
            ],
            'blocks' => [
                [
                    'id' => $block->id,
                    'name' => $block->name,
                    'position_x' => $block->position_x,
                    'position_y' => $block->position_y,
                    'rotation' => $block->rotation,
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $stageId = Block::where('room_id', $this->room->id)->where('type', 'stage')->value('id');
        $markerId = Block::where('room_id', $this->room->id)->where('type', 'comment')->value('id');

        $response->assertSessionHas('blockIdMap', [
            'temp-1' => $stageId,
            'temp-2' => $markerId,
        ]);
    }

    /** @test */
    public function layout_update_rejects_block_id_from_another_room()
    {
        $this->actingAs($this->admin);
        $otherRoom = Room::factory()->create();
        $foreignBlock = Block::factory()->seating()->create(['room_id' => $otherRoom->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                [
                    'id' => $foreignBlock->id,
                    'name' => 'Block',
                    'position_x' => 0,
                    'position_y' => 0,
                    'rotation' => 0,
                ],
            ],
        ])->assertSessionHasErrors('blocks.0.id');

        $this->assertDatabaseHas('blocks', [
            'id' => $foreignBlock->id,
            'name' => $foreignBlock->name,
            'room_id' => $otherRoom->id,
        ]);
    }

    /** @test */
    public function layout_update_rejects_invalid_block_ids()
    {
        $this->actingAs($this->admin);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                [
                    'id' => 'not-a-id',
                    'name' => 'Nope',
                    'position_x' => 0,
                    'position_y' => 0,
                    'rotation' => 0,
                ],
            ],
        ])->assertSessionHasErrors('blocks.0.id');

        $this->assertDatabaseMissing('blocks', ['name' => 'Nope']);
    }

    /** @test */
    public function admin_can_create_new_seating_block()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.rooms.blocks.create', $this->room->id), [
            'name' => 'New Block',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Block created successfully!');

        $this->assertDatabaseHas('blocks', [
            'room_id' => $this->room->id,
            'name' => 'New Block',
            'type' => 'seating',
            'position_x' => 0,
            'position_y' => 0,
            'rotation' => 0,
        ]);
    }

    /** @test */
    public function create_block_validates_name()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.rooms.blocks.create', $this->room->id), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function create_block_sets_correct_order()
    {
        $this->actingAs($this->admin);

        // Create existing blocks
        Block::factory()->seating()->create(['room_id' => $this->room->id, 'order' => 1]);
        Block::factory()->seating()->create(['room_id' => $this->room->id, 'order' => 3]);

        $response = $this->post(route('admin.rooms.blocks.create', $this->room->id), [
            'name' => 'New Block',
        ]);

        $response->assertRedirect();

        $newBlock = Block::where('name', 'New Block')->first();
        $this->assertEquals(4, $newBlock->order); // Should be max order + 1
    }

    /** @test */
    public function admin_can_delete_seating_block()
    {
        $this->actingAs($this->admin);

        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);

        $response = $this->delete(route('admin.rooms.blocks.delete', ['room' => $this->room->id, 'block' => $block->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Block deleted successfully!');

        // Verify cascade deletion
        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('rows', ['id' => $row->id]);
        $this->assertDatabaseMissing('seats', ['id' => $seat->id]);
    }

    /** @test */
    public function cannot_delete_non_existent_block()
    {
        $this->actingAs($this->admin);

        $response = $this->delete(route('admin.rooms.blocks.delete', ['room' => $this->room->id, 'block' => 999]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Block not found.');
    }

    /** @test */
    public function layout_update_creates_new_stage_blocks()
    {
        $this->actingAs($this->admin);

        // Create a seating block first so we have something for the required blocks array
        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $updateData = [
            'stageBlocks' => [
                [
                    'id' => null, // New stage block
                    'name' => 'New Stage',
                    'position_x' => 3,
                    'position_y' => 2,
                ],
            ],
            'blocks' => [
                [
                    'id' => $block->id,
                    'name' => $block->name,
                    'position_x' => $block->position_x,
                    'position_y' => $block->position_y,
                    'rotation' => $block->rotation,
                ],
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $updateData);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Room layout updated successfully!');

        $this->assertDatabaseHas('blocks', [
            'room_id' => $this->room->id,
            'name' => 'New Stage',
            'type' => 'stage',
            'position_x' => 3,
            'position_y' => 2,
        ]);
    }

    /** @test */
    public function layout_update_deletes_removed_stage_blocks()
    {
        $this->actingAs($this->admin);

        $stageBlock1 = Block::factory()->stage()->create(['room_id' => $this->room->id]);
        $stageBlock2 = Block::factory()->stage()->create(['room_id' => $this->room->id]);

        // Create a seating block for the required blocks array
        $seatingBlock = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        // Only include one stage block in update (delete the other)
        $updateData = [
            'stageBlocks' => [
                [
                    'id' => $stageBlock1->id,
                    'name' => $stageBlock1->name,
                    'position_x' => $stageBlock1->position_x,
                    'position_y' => $stageBlock1->position_y,
                ],
            ],
            'blocks' => [
                [
                    'id' => $seatingBlock->id,
                    'name' => $seatingBlock->name,
                    'position_x' => $seatingBlock->position_x,
                    'position_y' => $seatingBlock->position_y,
                    'rotation' => $seatingBlock->rotation,
                ],
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $updateData);

        $response->assertRedirect();

        // Verify one stage block remains, other is deleted
        $this->assertDatabaseHas('blocks', ['id' => $stageBlock1->id]);
        $this->assertDatabaseMissing('blocks', ['id' => $stageBlock2->id]);
    }

    /** @test */
    public function layout_update_reconciles_marker_blocks()
    {
        $this->actingAs($this->admin);

        $keptEntrance = Block::factory()->create([
            'room_id' => $this->room->id,
            'type' => 'entrance',
            'name' => 'Old Entrance',
            'position_x' => -1,
            'position_y' => 2,
            'rotation' => 0,
        ]);
        $removedComment = Block::factory()->create([
            'room_id' => $this->room->id,
            'type' => 'comment',
            'name' => 'Stale Note',
        ]);

        $seatingBlock = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $updateData = [
            'markerBlocks' => [
                [
                    'id' => $keptEntrance->id,
                    'type' => 'entrance',
                    'name' => 'Main Entrance',
                    'position_x' => -1,
                    'position_y' => 3,
                    'rotation' => 90,
                ],
                [
                    'id' => null,
                    'type' => 'comment',
                    'name' => 'New Note',
                    'position_x' => 4,
                    'position_y' => 4,
                    'rotation' => 0,
                ],
            ],
            'blocks' => [
                [
                    'id' => $seatingBlock->id,
                    'name' => $seatingBlock->name,
                    'position_x' => $seatingBlock->position_x,
                    'position_y' => $seatingBlock->position_y,
                    'rotation' => $seatingBlock->rotation,
                ],
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $updateData);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Room layout updated successfully!');

        // Existing marker updated in place
        $this->assertDatabaseHas('blocks', [
            'id' => $keptEntrance->id,
            'type' => 'entrance',
            'name' => 'Main Entrance',
            'position_y' => 3,
            'rotation' => 90,
        ]);

        // New marker created
        $this->assertDatabaseHas('blocks', [
            'room_id' => $this->room->id,
            'type' => 'comment',
            'name' => 'New Note',
            'position_x' => 4,
            'position_y' => 4,
        ]);

        // Marker absent from submission is deleted
        $this->assertDatabaseMissing('blocks', ['id' => $removedComment->id]);
    }

    /** @test */
    public function reconcile_updates_order_on_existing_blocks()
    {
        $this->actingAs($this->admin);

        $stage1 = Block::factory()->stage()->create(['room_id' => $this->room->id, 'order' => 99]);
        $stage2 = Block::factory()->stage()->create(['room_id' => $this->room->id, 'order' => 98]);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        // Submit with reversed order
        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'stageBlocks' => [
                ['id' => $stage2->id, 'name' => $stage2->name, 'position_x' => $stage2->position_x, 'position_y' => $stage2->position_y],
                ['id' => $stage1->id, 'name' => $stage1->name, 'position_x' => $stage1->position_x, 'position_y' => $stage1->position_y],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => $seating->position_x, 'position_y' => $seating->position_y, 'rotation' => $seating->rotation],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('blocks', ['id' => $stage2->id, 'order' => 0]);
        $this->assertDatabaseHas('blocks', ['id' => $stage1->id, 'order' => 1]);
    }

    /** @test */
    public function reconcile_deletes_all_blocks_when_empty_array_submitted()
    {
        $this->actingAs($this->admin);

        $stage1 = Block::factory()->stage()->create(['room_id' => $this->room->id]);
        $stage2 = Block::factory()->stage()->create(['room_id' => $this->room->id]);
        $entrance = Block::factory()->entrance()->create(['room_id' => $this->room->id]);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'stageBlocks' => [],
            'markerBlocks' => [],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => $seating->position_x, 'position_y' => $seating->position_y, 'rotation' => $seating->rotation],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('blocks', ['id' => $stage1->id]);
        $this->assertDatabaseMissing('blocks', ['id' => $stage2->id]);
        $this->assertDatabaseMissing('blocks', ['id' => $entrance->id]);
        $this->assertDatabaseHas('blocks', ['id' => $seating->id]);
    }

    /** @test */
    public function reconcile_assigns_contiguous_order_to_new_and_existing_blocks()
    {
        $this->actingAs($this->admin);

        $existing = Block::factory()->entrance()->create(['room_id' => $this->room->id, 'order' => 5]);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'markerBlocks' => [
                ['id' => null, 'type' => 'comment', 'name' => 'A', 'position_x' => 1, 'position_y' => 1, 'rotation' => 0],
                ['id' => $existing->id, 'type' => 'entrance', 'name' => $existing->name, 'position_x' => $existing->position_x, 'position_y' => $existing->position_y, 'rotation' => 0],
                ['id' => null, 'type' => 'comment', 'name' => 'B', 'position_x' => 2, 'position_y' => 2, 'rotation' => 0],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => $seating->position_x, 'position_y' => $seating->position_y, 'rotation' => $seating->rotation],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('blocks', ['name' => 'A', 'order' => 0]);
        $this->assertDatabaseHas('blocks', ['id' => $existing->id, 'order' => 1]);
        $this->assertDatabaseHas('blocks', ['name' => 'B', 'order' => 2]);
    }

    /** @test */
    public function layout_update_validates_marker_block_type()
    {
        $this->actingAs($this->admin);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'markerBlocks' => [
                ['id' => null, 'type' => 'seating', 'name' => 'Bad', 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
            ],
        ])->assertSessionHasErrors('markerBlocks.0.type');
    }

    /** @test */
    public function layout_update_validates_marker_block_rotation()
    {
        $this->actingAs($this->admin);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'markerBlocks' => [
                ['id' => null, 'type' => 'entrance', 'name' => 'Door', 'position_x' => -1, 'position_y' => 0, 'rotation' => 45],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
            ],
        ])->assertSessionHasErrors('markerBlocks.0.rotation');
    }

    /** @test */
    public function layout_update_rejects_marker_block_id_from_another_room()
    {
        $this->actingAs($this->admin);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $other = Room::factory()->create();
        $foreignMarker = Block::factory()->entrance()->create(['room_id' => $other->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'markerBlocks' => [
                ['id' => $foreignMarker->id, 'type' => 'entrance', 'name' => 'Door', 'position_x' => -1, 'position_y' => 0, 'rotation' => 0],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
            ],
        ])->assertSessionHasErrors('markerBlocks.0.id');

        $this->assertDatabaseHas('blocks', ['id' => $foreignMarker->id, 'room_id' => $other->id]);
    }

    /** @test */
    public function layout_editor_includes_marker_blocks_in_page_props()
    {
        $this->actingAs($this->admin);
        Block::factory()->entrance()->create(['room_id' => $this->room->id, 'name' => 'Main Door', 'order' => 0]);
        Block::factory()->comment()->create(['room_id' => $this->room->id, 'name' => 'Pillar', 'order' => 1]);

        $response = $this->get(route('admin.rooms.layout', $this->room->id));
        $response->assertOk();

        $props = $response->getOriginalContent()->getData()['page']['props'];
        $this->assertCount(2, $props['markerBlocks']);
        $this->assertEquals('Main Door', $props['markerBlocks'][0]['name']);
        $this->assertEquals('Pillar', $props['markerBlocks'][1]['name']);
    }

    /** @test */
    public function row_recreation_deletes_old_structure()
    {
        $this->actingAs($this->admin);

        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $oldRow = Row::factory()->create(['block_id' => $block->id, 'name' => 'Old Row']);
        $oldSeat = Seat::factory()->create(['row_id' => $oldRow->id]);

        $updateData = [
            'stageBlocks' => [],
            'blocks' => [
                [
                    'id' => $block->id,
                    'name' => $block->name,
                    'position_x' => $block->position_x,
                    'position_y' => $block->position_y,
                    'rotation' => $block->rotation,
                    'rowsData' => [
                        ['rowNumber' => 1, 'seatCount' => 5, 'isCustom' => false],
                    ],
                ],
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $updateData);

        $response->assertRedirect();

        // Verify old structure is deleted
        $this->assertDatabaseMissing('rows', ['id' => $oldRow->id]);
        $this->assertDatabaseMissing('seats', ['id' => $oldSeat->id]);

        // Verify new structure is created
        $this->assertDatabaseHas('rows', [
            'block_id' => $block->id,
            'name' => 'Row 1',
            'seats_count' => 5,
        ]);

        $newSeats = Seat::whereHas('row', function ($query) use ($block) {
            $query->where('block_id', $block->id);
        })->count();
        $this->assertEquals(5, $newSeats);
    }

    /** @test */
    public function omitting_stage_blocks_preserves_existing_stage_blocks()
    {
        $this->actingAs($this->admin);

        $stage = Block::factory()->stage()->create(['room_id' => $this->room->id]);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => $seating->position_x, 'position_y' => $seating->position_y, 'rotation' => $seating->rotation],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('blocks', ['id' => $stage->id]);
    }

    /** @test */
    public function omitting_marker_blocks_preserves_existing_marker_blocks()
    {
        $this->actingAs($this->admin);

        $entrance = Block::factory()->entrance()->create(['room_id' => $this->room->id]);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => $seating->position_x, 'position_y' => $seating->position_y, 'rotation' => $seating->rotation],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('blocks', ['id' => $entrance->id]);
    }

    /** @test */
    public function layout_update_rejects_comment_marker_with_negative_x()
    {
        $this->actingAs($this->admin);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'markerBlocks' => [
                ['id' => null, 'type' => 'comment', 'name' => 'Note', 'position_x' => -1, 'position_y' => 2, 'rotation' => 0],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
            ],
        ])->assertSessionHasErrors('markerBlocks.0');
    }

    /** @test */
    public function layout_update_rejects_entrance_marker_with_both_coordinates_negative()
    {
        $this->actingAs($this->admin);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'markerBlocks' => [
                ['id' => null, 'type' => 'entrance', 'name' => 'Door', 'position_x' => -1, 'position_y' => -1, 'rotation' => 0],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
            ],
        ])->assertSessionHasErrors('markerBlocks.0');
    }

    /** @test */
    public function layout_update_rejects_marker_coordinates_outside_grid()
    {
        $this->actingAs($this->admin);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'markerBlocks' => [
                ['id' => null, 'type' => 'comment', 'name' => 'Note', 'position_x' => 12, 'position_y' => 0, 'rotation' => 0],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
            ],
        ])->assertSessionHasErrors('markerBlocks.0.position_x');

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'markerBlocks' => [
                ['id' => null, 'type' => 'comment', 'name' => 'Note', 'position_x' => 0, 'position_y' => 8, 'rotation' => 0],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => 0, 'position_y' => 0, 'rotation' => 0],
            ],
        ])->assertSessionHasErrors('markerBlocks.0.position_y');
    }

    /** @test */
    public function layout_update_accepts_temp_id_for_new_stage_and_marker_blocks()
    {
        $this->actingAs($this->admin);
        $seating = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'stageBlocks' => [
                ['id' => 'temp-1', 'name' => 'New Stage', 'position_x' => 0, 'position_y' => 0],
            ],
            'markerBlocks' => [
                ['id' => 'temp-2', 'type' => 'entrance', 'name' => 'Door', 'position_x' => -1, 'position_y' => 1, 'rotation' => 0],
            ],
            'blocks' => [
                ['id' => $seating->id, 'name' => $seating->name, 'position_x' => $seating->position_x, 'position_y' => $seating->position_y, 'rotation' => $seating->rotation],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('blocks', ['room_id' => $this->room->id, 'type' => 'stage', 'name' => 'New Stage']);
        $this->assertDatabaseHas('blocks', ['room_id' => $this->room->id, 'type' => 'entrance', 'name' => 'Door']);
    }

    /** @test */
    public function non_admin_cannot_access_layout_routes()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        $response = $this->get(route('admin.rooms.layout', $this->room->id));
        $response->assertForbidden();

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), []);
        $response->assertForbidden();

        $response = $this->post(route('admin.rooms.blocks.create', $this->room->id), ['name' => 'Test']);
        $response->assertForbidden();

        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $response = $this->delete(route('admin.rooms.blocks.delete', ['room' => $this->room->id, 'block' => $block->id]));
        $response->assertForbidden();
    }

    /** @test */
    public function layout_update_persists_colspan_and_rowspan()
    {
        $this->actingAs($this->admin);

        $seatingBlock = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                [
                    'id' => $seatingBlock->id,
                    'name' => 'Block A',
                    'position_x' => 2,
                    'position_y' => 2,
                    'rotation' => 0,
                    'colspan' => 4,
                    'rowspan' => 2,
                    'rowsData' => [['rowNumber' => 1, 'seatCount' => 5, 'isCustom' => false]],
                ],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('blocks', ['id' => $seatingBlock->id, 'colspan' => 4, 'rowspan' => 2]);
    }

    /** @test */
    public function layout_update_defaults_colspan_and_rowspan_to_one()
    {
        $this->actingAs($this->admin);

        $seatingBlock = Block::factory()->seating()->create(['room_id' => $this->room->id, 'colspan' => 3, 'rowspan' => 3]);

        $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                [
                    'id' => $seatingBlock->id,
                    'name' => 'Block A',
                    'position_x' => 0,
                    'position_y' => 0,
                    'rotation' => 0,
                    'rowsData' => [['rowNumber' => 1, 'seatCount' => 5, 'isCustom' => false]],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('blocks', ['id' => $seatingBlock->id, 'colspan' => 1, 'rowspan' => 1]);
    }

    /** @test */
    public function layout_update_validates_span_range()
    {
        $this->actingAs($this->admin);

        $seatingBlock = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), [
            'blocks' => [
                [
                    'id' => $seatingBlock->id,
                    'name' => 'Block A',
                    'position_x' => 0,
                    'position_y' => 0,
                    'rotation' => 0,
                    'colspan' => 0,
                    'rowspan' => 99,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['blocks.0.colspan', 'blocks.0.rowspan']);
    }
}
