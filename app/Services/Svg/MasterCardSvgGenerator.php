<?php

namespace App\Services\Svg;

use Illuminate\Support\Collection;
use Mpdf\Mpdf;

class MasterCardSvgGenerator
{
    public function __construct(private SvgUtilities $svg) {}

    /**
     * Master room overview.
     *
     * @param  Collection  $blocks  Seating blocks with rows.seats.
     * @param  Collection  $stageBlocks  Stage blocks.
     * @param  array<int,bool>  $bookedSeatIds  Set of booked seat IDs (id => true).
     */
    public function render($blocks, $stageBlocks, array $bookedSeatIds, Mpdf $mpdf): string
    {
        $pitch = 15;
        $header = 30;    // space for the block name above its seat grid
        $inPad = 10;     // block border -> seat grid padding
        $cgap = 18;      // gap between grid cells
        $pad = 18;       // outer padding
        $stageSize = 90;

        $placed = fn ($b) => $b->position_x !== null && $b->position_y !== null && $b->position_x >= 0 && $b->position_y >= 0;
        $cells = [];
        foreach ($stageBlocks as $sb) {
            if ($placed($sb)) {
                $cells[] = ['x' => $sb->position_x, 'y' => $sb->position_y, 'type' => 'stage', 'block' => $sb, 'w' => $stageSize, 'h' => $stageSize];
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
                'w' => $inPad * 2 + $maxSeats * $pitch,
                'h' => $header + $inPad + max($rows->count(), 1) * $pitch,
            ];
        }
        if (empty($cells)) {
            return '';
        }

        $colW = $rowH = [];
        foreach ($cells as $c) {
            $colW[$c['x']] = max($colW[$c['x']] ?? 0, $c['w']);
            $rowH[$c['y']] = max($rowH[$c['y']] ?? 0, $c['h']);
        }
        $offsets = function (array $sizes) use ($cgap, $pad, $pitch) {
            $pos = [];
            $acc = $pad;
            for ($i = min(array_keys($sizes)); $i <= max(array_keys($sizes)); $i++) {
                $pos[$i] = $acc;
                $acc += ($sizes[$i] ?? $pitch) + $cgap;
            }

            return [$pos, $acc - $cgap + $pad];
        };
        [$colX, $width] = $offsets($colW);
        [$rowY, $height] = $offsets($rowH);

        $svg = '';
        foreach ($cells as $c) {
            $cw = $c['type'] === 'stage' ? $colW[$c['x']] : $c['w'];
            $bx = $colX[$c['x']] + ($colW[$c['x']] - $cw) / 2;
            $by = $rowY[$c['y']];
            $cxMid = $bx + $cw / 2;

            if ($c['type'] === 'stage') {
                $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="10" ry="10" fill="#000000" stroke="#000000" stroke-width="2"/>', $bx, $by, $cw, $c['h']);
                $sz = SvgUtilities::SIZE_STAGE_LABEL;
                $svg .= $this->svg->centeredLabel($mpdf, $c['block']->name ?: 'Stage', $sz, '#ffffff', $cxMid, $by + $c['h'] / 2 + $sz * SvgUtilities::BASELINE_FACTOR, $cw - 12);

                continue;
            }

            $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="10" ry="10" fill="none" stroke="#000000" stroke-width="3"/>', $bx, $by, $c['w'], $c['h']);
            $svg .= $this->svg->centeredLabel($mpdf, $c['block']->name, SvgUtilities::SIZE_BLOCK_LABEL, '#000000', $cxMid, $by + 22, $c['w'] - 12);

            $dot = $pitch * 0.7;
            $gridWidth = $c['maxSeats'] * $pitch;
            $gridLeft = $cxMid - $gridWidth / 2;
            $gridTop = $by + $header + $pitch / 2;
            foreach ($c['rows'] as $ri => $row) {
                $seats = $row->seats->sortBy('number')->values();
                $rowWidth = $seats->count() * $pitch;
                $alignment = $row->alignment ?? 'center';
                $rowLeft = match ($alignment) {
                    'left' => $gridLeft + $pitch / 2,
                    'right' => $gridLeft + $gridWidth - $rowWidth + $pitch / 2,
                    default => $cxMid - $rowWidth / 2 + $pitch / 2,
                };
                foreach ($seats as $ci => $seat) {
                    $svg .= sprintf(
                        '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2" ry="2" fill="%s" stroke="#000000" stroke-width="1"/>',
                        $rowLeft + $ci * $pitch - $dot / 2, $gridTop + $ri * $pitch - $dot / 2, $dot, $dot,
                        isset($bookedSeatIds[$seat->id]) ? '#000000' : '#ffffff'
                    );
                }
            }
        }

        $scale = min(200 / $width, 110 / $height);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%smm" height="%smm">%s</svg>',
            $width, $height, round($width * $scale, 1), round($height * $scale, 1), $svg
        );
    }
}
