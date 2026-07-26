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

    private const ENTRANCE_BAND = 44; // thickness of an entrance band

    private const ARROW_LABEL_GAP = 22; // gap between an entrance label and its arrows

    public function __construct(private SvgUtilities $svg) {}

    /**
     * Master room overview.
     *
     * @param  Collection  $blocks  Seating blocks with rows.seats.
     * @param  Collection  $stageBlocks  Stage blocks.
     * @param  Collection  $markerBlocks  Entrance and comment marker blocks.
     * @param  array<int,bool>  $bookedSeatIds  Set of booked seat IDs.
     */
    public function render(Collection $blocks, Collection $stageBlocks, Collection $markerBlocks, array $bookedSeatIds, Mpdf $mpdf): string
    {
        $cells = $this->cells($blocks, $stageBlocks, $markerBlocks);
        if (empty($cells)) {
            return '';
        }

        [$colW, $rowH] = $this->gridSizes($cells);

        $occupied = $this->occupiedRange($cells);

        // Reserve a grid line for each entrance so its row/column gets a slot even when
        // no other block shares that line.
        foreach ($markerBlocks as $mb) {
            if ($mb->type !== 'entrance') {
                continue;
            }
            if ((int) $mb->position_x === -1 && $mb->position_y >= 0) {
                $rowH[$mb->position_y] = max($rowH[$mb->position_y] ?? 0, self::ENTRANCE_BAND);
            }
            if ((int) $mb->position_y === -1 && $mb->position_x >= 0) {
                $colW[$mb->position_x] = max($colW[$mb->position_x] ?? 0, self::ENTRANCE_BAND);
            }
        }

        [$colX, $width] = $this->gridLinePositions($colW);
        [$rowY, $height] = $this->gridLinePositions($rowH);

        $entranceSvg = $this->entranceMarkers($markerBlocks, $occupied, $colX, $rowY, $colW, $rowH, $mpdf);

        $spanPx = fn (int $start, int $span, array $starts, array $sizes): float => $starts[$start + $span - 1] + $sizes[$start + $span - 1] - $starts[$start];

        $svg = '';
        foreach ($cells as $c) {
            $bx = $colX[$c['x']];
            $by = $rowY[$c['y']];
            $cw = $spanPx($c['x'], $c['colSpan'], $colX, $colW);
            $ch = $spanPx($c['y'], $c['rowSpan'], $rowY, $rowH);
            $svg .= match ($c['type']) {
                'stage' => $this->stageCell($c, $bx, $by, $cw, $ch, $mpdf),
                'comment' => $this->commentCell($c, $bx, $by, $cw, $ch, $mpdf),
                default => $this->seatingCell($c, $bx, $by, $bookedSeatIds, $mpdf),
            };
        }

        $scale = min(200 / $width, 110 / $height);

        return $this->svg->document($width, $height, $width * $scale, $height * $scale, $svg.$entranceSvg);
    }

    /**
     * Placed blocks as grid cells.
     *
     * @return array<int,array<string,mixed>>
     */
    private function cells(Collection $blocks, Collection $stageBlocks, Collection $markerBlocks): array
    {
        $placed = fn ($b) => $b->position_x !== null && $b->position_y !== null && $b->position_x >= 0 && $b->position_y >= 0;

        $span = fn ($b, string $key) => max((int) ($b->{$key} ?? 1), 1);

        $cells = [];
        foreach ($stageBlocks as $sb) {
            if ($placed($sb)) {
                $cells[] = ['x' => $sb->position_x, 'y' => $sb->position_y, 'type' => 'stage', 'block' => $sb, 'w' => self::STAGE_SIZE, 'h' => self::STAGE_SIZE, 'colSpan' => $span($sb, 'colspan'), 'rowSpan' => $span($sb, 'rowspan')];
            }
        }
        foreach ($markerBlocks as $mb) {
            if ($mb->type === 'comment' && $placed($mb)) {
                $cells[] = ['x' => $mb->position_x, 'y' => $mb->position_y, 'type' => 'comment', 'block' => $mb, 'w' => self::STAGE_SIZE, 'h' => self::STAGE_SIZE, 'colSpan' => $span($mb, 'colspan'), 'rowSpan' => $span($mb, 'rowspan')];
            }
        }
        foreach ($blocks as $b) {
            if (! $placed($b)) {
                continue;
            }
            $rows = $b->rows->sortBy('order')->values();
            $maxSeats = max((int) $rows->max(fn ($r) => $r->seats->count()), 1);
            $rotation = (int) ($b->rotation ?? 0);

            $vertical = $rotation === 90 || $rotation === 270;
            $gridCols = $vertical ? max($rows->count(), 1) : $maxSeats;
            $gridRows = $vertical ? $maxSeats : max($rows->count(), 1);

            $cells[] = [
                'x' => $b->position_x, 'y' => $b->position_y, 'type' => 'seating', 'block' => $b, 'rows' => $rows,
                'maxSeats' => $maxSeats, 'rotation' => $rotation,
                'gridCols' => $gridCols, 'gridRows' => $gridRows,
                'w' => self::IN_PAD * 2 + $gridCols * self::PITCH,
                'h' => self::HEADER + self::IN_PAD + $gridRows * self::PITCH,
                'colSpan' => $span($b, 'colspan'), 'rowSpan' => $span($b, 'rowspan'),
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
            for ($dx = 0; $dx < $c['colSpan']; $dx++) {
                $colW[$c['x'] + $dx] = max($colW[$c['x'] + $dx] ?? 0, $c['w'] / $c['colSpan']);
            }
            for ($dy = 0; $dy < $c['rowSpan']; $dy++) {
                $rowH[$c['y'] + $dy] = max($rowH[$c['y'] + $dy] ?? 0, $c['h'] / $c['rowSpan']);
            }
        }

        return [$colW, $rowH];
    }

    /**
     * Min/max column and row occupied by real cells (seating, stage, comment).
     *
     * @param  array<int,array<string,mixed>>  $cells
     * @return array{minX:int, maxX:int, minY:int, maxY:int}
     */
    private function occupiedRange(array $cells): array
    {
        $minX = $minY = PHP_INT_MAX;
        $maxX = $maxY = PHP_INT_MIN;
        foreach ($cells as $c) {
            $minX = min($minX, $c['x']);
            $minY = min($minY, $c['y']);
            $maxX = max($maxX, $c['x'] + $c['colSpan'] - 1);
            $maxY = max($maxY, $c['y'] + $c['rowSpan'] - 1);
        }

        return ['minX' => $minX, 'maxX' => $maxX, 'minY' => $minY, 'maxY' => $maxY];
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

    private function stageCell(array $c, float $bx, float $by, float $cw, float $ch, Mpdf $mpdf): string
    {
        $sz = SvgUtilities::SIZE_STAGE_LABEL;

        return $this->svg->rect($bx, $by, $cw, $ch, '#000000', 2, 10)
            .$this->svg->centeredLabelX($mpdf, $c['block']->name ?: 'Stage', $sz, '#ffffff', $bx + $cw / 2, $by + $ch / 2 + $sz * SvgUtilities::BASELINE_FACTOR, $cw - 12);
    }

    private function commentCell(array $c, float $bx, float $by, float $cw, float $ch, Mpdf $mpdf): string
    {
        $sz = SvgUtilities::SIZE_STAGE_LABEL;

        return $this->svg->rect($bx, $by, $cw, $ch, 'none', 2, 10)
            .$this->svg->centeredLabelX($mpdf, $c['block']->name, $sz, '#000000', $bx + $cw / 2, $by + $ch / 2 + $sz * SvgUtilities::BASELINE_FACTOR, $cw - 12);
    }

    /**
     * @param  array{minX:int, maxX:int, minY:int, maxY:int}  $occupied
     * @param  array<int,float>  $colX
     * @param  array<int,float>  $rowY
     * @param  array<int,float>  $colW
     * @param  array<int,float>  $rowH
     */
    private function entranceMarkers(Collection $markerBlocks, array $occupied, array $colX, array $rowY, array $colW, array $rowH, Mpdf $mpdf): string
    {
        if (empty($colX) || empty($rowY)) {
            return '';
        }

        $spanLeft = $colX[$occupied['minX']];
        $spanRight = $colX[$occupied['maxX']] + $colW[$occupied['maxX']];
        $spanTop = $rowY[$occupied['minY']];
        $spanBottom = $rowY[$occupied['maxY']] + $rowH[$occupied['maxY']];

        $band = self::ENTRANCE_BAND;
        $svg = '';

        foreach ($markerBlocks as $mb) {
            if ($mb->type !== 'entrance') {
                continue;
            }
            $rotation = (int) ($mb->rotation ?? 0);

            if ((int) $mb->position_x === -1 && isset($rowY[$mb->position_y])) {
                $y = $rowY[$mb->position_y] + ($rowH[$mb->position_y] - $band) / 2;
                $svg .= $this->entranceBand($spanLeft, $y, $spanRight - $spanLeft, $band, 'row', $rotation, $mb->name, $mpdf);
            }

            if ((int) $mb->position_y === -1 && isset($colX[$mb->position_x])) {
                $x = $colX[$mb->position_x] + ($colW[$mb->position_x] - $band) / 2;
                $svg .= $this->entranceBand($x, $spanTop, $band, $spanBottom - $spanTop, 'column', $rotation, $mb->name, $mpdf);
            }
        }

        return $svg;
    }

    private function arrowDirection(int $rotation): string
    {
        return match ($rotation) {
            90 => 'right',
            180 => 'down',
            270 => 'left',
            default => 'up',
        };
    }

    private function entranceBand(float $x, float $y, float $w, float $h, string $orientation, int $rotation, string $name, Mpdf $mpdf): string
    {
        $svg = $this->svg->rect($x, $y, $w, $h, '#ffffff', 2, 8, '6 4');

        $sz = SvgUtilities::SIZE_BLOCK_LABEL;
        $dir = $this->arrowDirection($rotation);
        $label = $name ?: 'Entrance';
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;

        $availableLength = $orientation === 'row' ? $w : $h;
        $labelW = $this->svg->textWidth($mpdf, $label, $sz);
        $effectiveLabelW = min($labelW, $availableLength);
        $labelSvg = $this->svg->centeredLabelX($mpdf, $label, $sz, '#000000', $cx, $cy + $sz * SvgUtilities::BASELINE_FACTOR, $availableLength);
        $textAngle = $rotation === 270 ? -90 : 90;
        $svg .= $orientation === 'row'
            ? $labelSvg
            : sprintf('<g transform="rotate(%d %.1f %.1f)">%s</g>', $textAngle, $cx, $cy, $labelSvg);

        $arrowGap = $effectiveLabelW / 2 + self::ARROW_LABEL_GAP;
        [$before, $after] = $orientation === 'row'
            ? [[$cx - $arrowGap, $cy], [$cx + $arrowGap, $cy]]
            : [[$cx, $cy - $arrowGap], [$cx, $cy + $arrowGap]];

        $svg .= $this->svg->centeredArrowHead($before[0], $before[1], $dir);
        $svg .= $this->svg->centeredArrowHead($after[0], $after[1], $dir);

        return $svg;
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
        $gridCols = $c['gridCols'];
        $rowCount = max($c['rows']->count(), 1);
        $gridWidth = $gridCols * self::PITCH;
        $gridLeft = $cxMid - $gridWidth / 2;
        $gridTop = $by + self::HEADER + self::PITCH / 2;
        $vertical = $c['rotation'] === 90 || $c['rotation'] === 270;

        $svg = '';
        foreach ($c['rows'] as $ri => $row) {
            $seats = $row->seats->sortBy('number')->values();
            $count = $seats->count();

            $seatSlack = ($vertical ? $c['gridRows'] : $gridCols) - $count;
            $seatStart = match ($row->alignment ?? 'center') {
                'left' => 0,
                'right' => $seatSlack,
                default => $seatSlack / 2,
            };

            foreach ($seats as $ci => $seat) {
                $seatPos = $seatStart + $ci;
                [$gc, $gr] = match ($c['rotation']) {
                    90 => [$rowCount - 1 - $ri, $seatPos],
                    270 => [$ri, $c['gridRows'] - 1 - $seatPos],
                    180 => [$gridCols - 1 - $seatPos, $rowCount - 1 - $ri],
                    default => [$seatPos, $ri],
                };
                $svg .= $this->svg->rect(
                    $gridLeft + $gc * self::PITCH + self::PITCH / 2 - $dot / 2,
                    $gridTop + $gr * self::PITCH - $dot / 2, $dot, $dot,
                    isset($bookedSeatIds[$seat->id]) ? '#000000' : '#ffffff', 1, 2
                );
            }
        }

        return $svg;
    }
}
