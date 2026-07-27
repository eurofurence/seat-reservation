<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Only merge the ones that were merged visually before this migration
    private const SPAN_TYPES = ['stage', 'comment'];

    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->integer('colspan')->default(1)->after('rotation');
            $table->integer('rowspan')->default(1)->after('colspan');
        });

        $this->mergeAdjacentBlocks();
    }

    public function down(): void
    {
        $this->splitSpannedBlocks();

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn(['colspan', 'rowspan']);
        });
    }

    private function mergeAdjacentBlocks(): void
    {
        foreach ($this->roomIds() as $roomId) {
            foreach (self::SPAN_TYPES as $type) {
                $this->mergeRoomType($roomId, $type);
            }
        }
    }

    private function mergeRoomType(int $roomId, string $type): void
    {
        $blocks = DB::table('blocks')
            ->where('room_id', $roomId)
            ->where('type', $type)
            ->where('position_x', '>=', 0)
            ->where('position_y', '>=', 0)
            ->orderBy('position_y')
            ->orderBy('position_x')
            ->get(['id', 'name', 'position_x', 'position_y']);

        $grid = [];
        foreach ($blocks as $block) {
            $grid["{$block->position_x},{$block->position_y}"] = $block;
        }

        $consumed = [];
        $free = fn (int $x, int $y, $ref) => ! isset($consumed["{$x},{$y}"])
            && ($grid["{$x},{$y}"] ?? null)?->name === $ref->name;

        foreach ($blocks as $block) {
            $x = $block->position_x;
            $y = $block->position_y;
            if (isset($consumed["{$x},{$y}"])) {
                continue;
            }

            $colspan = 1;
            while ($free($x + $colspan, $y, $block)) {
                $colspan++;
            }

            $rowspan = 1;
            while (collect(range(0, $colspan - 1))->every(fn ($dc) => $free($x + $dc, $y + $rowspan, $block))) {
                $rowspan++;
            }

            if ($colspan === 1 && $rowspan === 1) {
                continue;
            }

            $coveredIds = [];
            for ($dr = 0; $dr < $rowspan; $dr++) {
                for ($dc = 0; $dc < $colspan; $dc++) {
                    if ($dr || $dc) {
                        $consumed[($x + $dc).','.($y + $dr)] = true;
                        $coveredIds[] = $grid[($x + $dc).','.($y + $dr)]->id;
                    }
                }
            }

            DB::table('blocks')->where('id', $block->id)->update(compact('colspan', 'rowspan'));
            DB::table('blocks')->whereIn('id', $coveredIds)->delete();
        }
    }

    private function splitSpannedBlocks(): void
    {
        $spanned = DB::table('blocks')
            ->whereIn('type', self::SPAN_TYPES)
            ->where(function ($query) {
                $query->where('colspan', '>', 1)->orWhere('rowspan', '>', 1);
            })
            ->get();

        foreach ($spanned as $block) {
            $clones = [];
            for ($dr = 0; $dr < $block->rowspan; $dr++) {
                for ($dc = 0; $dc < $block->colspan; $dc++) {
                    if ($dr || $dc) {
                        $clones[] = [
                            'room_id' => $block->room_id,
                            'name' => $block->name,
                            'type' => $block->type,
                            'position_x' => $block->position_x + $dc,
                            'position_y' => $block->position_y + $dr,
                            'rotation' => $block->rotation,
                            'order' => $block->order,
                        ];
                    }
                }
            }

            DB::table('blocks')->insert($clones);
        }
    }

    /**
     * @return array<int,int>
     */
    private function roomIds(): array
    {
        return DB::table('blocks')->distinct()->pluck('room_id')->all();
    }
};
