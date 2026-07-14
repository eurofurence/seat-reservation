<?php

namespace App\Services\Svg;

use App\Models\Block;

class OrderCardSvgGenerator
{
    public function __construct(private SvgUtilities $svg) {}

    /**
     * Block preview for the order-card divider.
     *
     * @param  array<int,bool>  $bookedSeatIds  Set of booked seat IDs (id => true).
     */
    public function render(Block $block, array $bookedSeatIds): string
    {
        $rows = $block->rows->sortBy('order')->values();
        if ($rows->isEmpty()) {
            return '';
        }

        $box = 64;
        $gap = 12;
        $pad = 46;
        $turn = 80;   // reach of the rounded u turns past a row edge
        $radius = 12;
        $cr = 18;     // u turn corner radius
        $stride = $box + $gap;
        $rowCount = $rows->count();
        $maxSeats = max((int) $rows->max(fn ($r) => $r->seats->count()), 1);
        $left = $pad + $turn;
        $width = $left * 2 + $maxSeats * $stride - $gap;
        $height = $pad * 2 + $rowCount * $stride - $gap;

        $cx = fn (int $col) => $left + $col * $stride + $box / 2;
        $cy = fn (int $ri) => $pad + $ri * $stride + $box / 2;

        $unbooked = $booked = '';
        foreach ($rows as $ri => $row) {
            foreach ($row->seats->sortBy('number')->values() as $col => $seat) {
                $x = $left + $col * $stride;
                $y = $pad + $ri * $stride;
                $rect = fn ($fill) => sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" rx="%d" ry="%d" fill="%s" stroke="#000000" stroke-width="2"/>',
                    $x, $y, $box, $box, $radius, $radius, $fill
                );
                if (isset($bookedSeatIds[$seat->id])) {
                    $booked .= $rect('#000000').sprintf(
                        '<text x="%.1f" y="%.1f" fill="#ffffff" font-size="30" font-weight="bold" font-family="sans-serif" text-anchor="middle">%s</text>',
                        $x + $box / 2, $y + $box / 2 + 11, e($seat->label)
                    );
                } else {
                    $unbooked .= $rect('none');
                }
            }
        }

        $d = '';
        $turnArrows = [];
        for ($ri = 0; $ri < $rowCount; $ri++) {
            $count = $rows[$ri]->seats->count();
            if ($count < 1) {
                continue;
            }
            $ltr = $ri % 2 === 0;
            $y = $cy($ri);
            $startX = $ltr ? $cx(0) : $cx($count - 1);
            $endX = $ltr ? $cx($count - 1) : $cx(0);
            $d .= sprintf('%s %.1f %.1f L %.1f %.1f ', $d === '' ? 'M' : 'L', $startX, $y, $endX, $y);

            if ($ri < $rowCount - 1) {
                $nextY = $cy($ri + 1);
                $dir = $ltr ? 1 : -1;
                $outX = $endX + $dir * $turn;
                $sweep = $ltr ? 1 : 0;
                $d .= sprintf('L %.1f %.1f A %.1f %.1f 0 0 %d %.1f %.1f L %.1f %.1f A %.1f %.1f 0 0 %d %.1f %.1f L %.1f %.1f ',
                    $outX - $dir * $cr, $y, $cr, $cr, $sweep, $outX, $y + $cr,
                    $outX, $nextY - $cr, $cr, $cr, $sweep, $outX - $dir * $cr, $nextY, $endX, $nextY);
                $flatX = $outX - $dir * $cr - $dir * 6;
                $turnArrows[] = [$flatX, $y, $ltr ? 'right' : 'left'];
                $turnArrows[] = [$flatX, $nextY, $ltr ? 'left' : 'right'];
            }
        }

        $endStubX = null;
        $lastRi = $rowCount - 1;
        if ($lastRi >= 0 && $rows[$lastRi]->seats->count() > 0) {
            $count = $rows[$lastRi]->seats->count();
            $endDir = $lastRi % 2 === 0 ? 1 : -1;
            $rowEndX = $lastRi % 2 === 0 ? $cx($count - 1) : $cx(0);
            $endStubX = $rowEndX + $endDir * ($box / 2 + 24);
            $d .= sprintf('L %.1f %.1f ', $endStubX, $cy($lastRi));
        }
        $path = $d === '' ? '' : sprintf('<path d="%s" fill="none" stroke="#000000" stroke-width="4"/>', trim($d));

        $arrows = '';
        if ($rowCount > 0 && $rows[0]->seats->count() > 0) {
            $sx = $cx(0);
            $labelY = $cy(0) - $box / 2 - 26;
            $arrows .= sprintf('<text x="%.1f" y="%.1f" fill="#000000" font-size="22" font-weight="bold" font-family="sans-serif" text-anchor="middle">START</text>', $sx, $labelY);
            $arrows .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#000000" stroke-width="4"/>', $sx, $labelY + 6, $sx, $cy(0) - $box / 2 + 2);
            $arrows .= $this->svg->arrowHead($sx, $cy(0) - $box / 2 + 4, 'down');
        }
        foreach ($rows as $ri => $row) {
            if ($row->seats->count() < 1) {
                continue;
            }
            $mx = $cx((int) floor(($maxSeats - 1) / 2)) + $stride / 2;
            $arrows .= $this->svg->arrowHead($mx, $cy($ri), $ri % 2 === 0 ? 'right' : 'left');
        }
        foreach ($turnArrows as [$tx, $ty, $tdir]) {
            $arrows .= $this->svg->arrowHead($tx, $ty, $tdir);
        }
        if ($endStubX !== null) {
            $arrows .= $this->svg->arrowHead($endStubX, $cy($lastRi), $lastRi % 2 === 0 ? 'right' : 'left');
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" style="width:100%%;height:auto;">%s%s%s%s</svg>',
            $width, $height, $unbooked, $path, $booked, $arrows
        );
    }
}
