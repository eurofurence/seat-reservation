<?php

namespace App\Observers;

use App\Models\Block;

class BlockObserver
{
    public function created(Block $block): void
    {
        $this->updateRows($block);
    }

    public function updated(Block $block): void
    {
        $this->updateRows($block);
    }

    public function deleted(Block $block): void
    {
    }

    public function restored(Block $block): void
    {
    }

    private function updateRows(Block $block)
    {
        if ($block->isDirty('row_count')) {
            $currentRowsCount = $block->rows()->count();
            $newRowsCount = $block->row_count;
            if ($currentRowsCount < $newRowsCount) {
                for ($i = $currentRowsCount; $i < $newRowsCount; $i++) {
                    $block->rows()->create([
                        'name' => ($i + 1),
                        'seat_count' => $block->default_seat_count,
                    ]);
                }
            } elseif ($currentRowsCount > $newRowsCount) {
                $block->rows()->orderBy('name', 'desc')->limit($currentRowsCount - $newRowsCount)->delete();
            }
        }
    }
}
