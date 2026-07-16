<?php

namespace App\Services\Svg;

use Illuminate\Support\Collection;
use Mpdf\Mpdf;

class MasterCardSvgGenerator
{
    private const PITCH = 15;       // seat spacing

    private const HEADER = 30;      // space above a block's seat grid for its name

    private const IN_PAD = 10;      // block border to seat grid

    private const CELL_GAP = 18;    // gap between grid cells

    private const PAD = 18;         // outer padding

    private const STAGE_SIZE = 90;

    public function __construct(private SvgUtilities $svg) {}

    /**
     * Master room overview.
     *
     * @param  Collection  $blocks  Seating blocks with rows.seats.
     * @param  Collection  $stageBlocks  Stage blocks.
     * @param  array<int,bool>  $bookedSeatIds  Set of booked seat IDs.
     */
    public function render(Collection $blocks, Collection $stageBlocks, array $bookedSeatIds, Mpdf $mpdf): string
    {
        $cells = $this->cells($blocks, $stageBlocks);
        if (empty($cells)) {
            return '';
        }

        [$colW, $rowH] = $this->gridSizes($cells);
        [$colX, $width] = $this->gridLinePositions($colW);
        [$rowY, $height] = $this->gridLinePositions($rowH);

        $svg = '';
        foreach ($cells as $c) {
            $cw = $c['type'] === 'stage' ? $colW[$c['x']] : $c['w'];
            $bx = $colX[$c['x']] + ($colW[$c['x']] - $cw) / 2;
            $by = $rowY[$c['y']];
            $svg .= $c['type'] === 'stage'
                ? $this->stageCell($c, $bx, $by, $cw, $mpdf)
                : $this->seatingCell($c, $bx, $by, $bookedSeatIds, $mpdf);
        }

        $scale = min(200 / $width, 110 / $height);

        return $this->svg->document($width, $height, $width * $scale, $height * $scale, $svg);
    }

    /**
     * Placed blocks as grid cells.
     *
     * @return array<int,array<string,mixed>>
     */
    private function cells(Collection $blocks, Collection $stageBlocks): array
    {
        $placed = fn ($b) => $b->position_x !== null && $b->position_y !== null && $b->position_x >= 0 && $b->position_y >= 0;

        $cells = [];
        foreach ($stageBlocks as $sb) {
            if ($placed($sb)) {
                $cells[] = ['x' => $sb->position_x, 'y' => $sb->position_y, 'type' => 'stage', 'block' => $sb, 'w' => self::STAGE_SIZE, 'h' => self::STAGE_SIZE];
            }
        }
        foreach ($blocks as $b) {
            if (! $placed($b)) {
                continue;
            }
            $rows = $b->rows->sortBy('order')->values();
            $maxSeats = max((int) $rows->max(fn ($r) => $r->seats->count()), 1);
            $cells[] = [
                'x' => $b->position_x, 'y' => $b->position_y, 'type' => 'seating', 'block' => $b, 'rows' => $rows,
                'maxSeats' => $maxSeats,
                'w' => self::IN_PAD * 2 + $maxSeats * self::PITCH,
                'h' => self::HEADER + self::IN_PAD + max($rows->count(), 1) * self::PITCH,
            ];
        }

        return $cells;
    }

    /**
     * @param  array<int,array<string,mixed>>  $cells
     * @return array{0: array<int,float>, 1: array<int,float>}
     */
    private function gridSizes(array $cells): array
    {
        $colW = $rowH = [];
        foreach ($cells as $c) {
            $colW[$c['x']] = max($colW[$c['x']] ?? 0, $c['w']);
            $rowH[$c['y']] = max($rowH[$c['y']] ?? 0, $c['h']);
        }

        return [$colW, $rowH];
    }

    /**
     * @param  array<int,float>  $sizes  Cell size per grid index
     * @return array{0: array<int,float>, 1: float} [start pixel per index, total length]
     */
    private function gridLinePositions(array $sizes): array
    {
        $starts = [];
        $cursor = self::PAD;
        for ($i = min(array_keys($sizes)); $i <= max(array_keys($sizes)); $i++) {
            $starts[$i] = $cursor;
            $cursor += ($sizes[$i] ?? self::PITCH) + self::CELL_GAP;
        }

        return [$starts, $cursor - self::CELL_GAP + self::PAD];
    }

    private function stageCell(array $c, float $bx, float $by, float $cw, Mpdf $mpdf): string
    {
        $sz = SvgUtilities::SIZE_STAGE_LABEL;

        return $this->svg->rect($bx, $by, $cw, $c['h'], '#000000', 2, 10)
            .$this->svg->centeredLabelX($mpdf, $c['block']->name ?: 'Stage', $sz, '#ffffff', $bx + $cw / 2, $by + $c['h'] / 2 + $sz * SvgUtilities::BASELINE_FACTOR, $cw - 12);
    }

    private function seatingCell(array $c, float $bx, float $by, array $bookedSeatIds, Mpdf $mpdf): string
    {
        $cxMid = $bx + $c['w'] / 2;

        return $this->svg->rect($bx, $by, $c['w'], $c['h'], 'none', 3, 10)
            .$this->svg->centeredLabelX($mpdf, $c['block']->name, SvgUtilities::SIZE_BLOCK_LABEL, '#000000', $cxMid, $by + 22, $c['w'] - 12)
            .$this->seatDots($c, $cxMid, $by, $bookedSeatIds);
    }

    private function seatDots(array $c, float $cxMid, float $by, array $bookedSeatIds): string
    {
        $dot = self::PITCH * 0.7;
        $gridWidth = $c['maxSeats'] * self::PITCH;
        $gridLeft = $cxMid - $gridWidth / 2;
        $gridTop = $by + self::HEADER + self::PITCH / 2;

        $svg = '';
        foreach ($c['rows'] as $ri => $row) {
            $seats = $row->seats->sortBy('number')->values();
            $rowWidth = $seats->count() * self::PITCH;
            $rowLeft = match ($row->alignment ?? 'center') {
                'left' => $gridLeft + self::PITCH / 2,
                'right' => $gridLeft + $gridWidth - $rowWidth + self::PITCH / 2,
                default => $cxMid - $rowWidth / 2 + self::PITCH / 2,
            };
            foreach ($seats as $ci => $seat) {
                $svg .= $this->svg->rect(
                    $rowLeft + $ci * self::PITCH - $dot / 2, $gridTop + $ri * self::PITCH - $dot / 2, $dot, $dot,
                    isset($bookedSeatIds[$seat->id]) ? '#000000' : '#ffffff', 1, 2
                );
            }
        }

        return $svg;
    }
}
