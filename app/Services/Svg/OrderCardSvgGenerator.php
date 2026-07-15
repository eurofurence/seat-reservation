<?php

namespace App\Services\Svg;

use App\Models\Block;
use Mpdf\Mpdf;

class OrderCardSvgGenerator
{
    public function __construct(private SvgUtilities $svg) {}

    /**
     * Block preview for the order-card divider.
     *
     * @param  array<int,bool>  $bookedSeatIds  Set of booked seat IDs (id => true); these
     *                                          boxes are filled black.
     * @param  array<int,bool>|null  $labelSeatIds  Set of seat IDs to label; defaults to
     *                                              the booked set. Booked boxes get a white
     *                                              label, unbooked ones a black label.
     */
    public function render(Block $block, array $bookedSeatIds, Mpdf $mpdf, ?array $labelSeatIds = null): string
    {
        $labelSeatIds ??= $bookedSeatIds;

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

        $rowOffset = function (int $ri) use ($rows, $maxSeats) {
            $count = $rows[$ri]->seats->count();
            $slack = $maxSeats - $count;
            if ($slack <= 0) {
                return 0.0;
            }

            return match ($rows[$ri]->alignment ?? 'center') {
                'left' => 0.0,
                'right' => (float) $slack,
                default => $slack / 2,
            };
        };

        $cx = fn (float $col) => $left + $col * $stride + $box / 2;
        $cy = fn (int $ri) => $pad + $ri * $stride + $box / 2;

        $under = $over = '';
        foreach ($rows as $ri => $row) {
            $off = $rowOffset($ri);
            foreach ($row->seats->sortBy('number')->values() as $col => $seat) {
                $x = $left + ($col + $off) * $stride;
                $y = $pad + $ri * $stride;
                $isBooked = isset($bookedSeatIds[$seat->id]);
                $rect = sprintf(
                    '<rect x="%.1f" y="%.1f" width="%d" height="%d" rx="%d" ry="%d" fill="%s" stroke="#000000" stroke-width="2"/>',
                    $x, $y, $box, $box, $radius, $radius, $isBooked ? '#000000' : 'none'
                );
                if (isset($labelSeatIds[$seat->id])) {
                    $sz = SvgUtilities::SIZE_SEAT_LABEL;
                    $over .= $rect.$this->svg->centeredLabel(
                        $mpdf, (string) $seat->label, $sz, $isBooked ? '#ffffff' : '#000000',
                        $x + $box / 2, $y + $box / 2 + $sz * SvgUtilities::BASELINE_FACTOR, $box - 8
                    );
                } else {
                    $under .= $rect;
                }
            }
        }

        $rowLeftCol = fn (int $r) => $rowOffset($r);
        $rowRightCol = fn (int $r) => $rowOffset($r) + $rows[$r]->seats->count() - 1;

        $d = '';
        $turnArrows = [];
        for ($ri = 0; $ri < $rowCount; $ri++) {
            $count = $rows[$ri]->seats->count();
            if ($count < 1) {
                continue;
            }
            $ltr = $ri % 2 === 0;
            $y = $cy($ri);
            $startX = $ltr ? $cx($rowLeftCol($ri)) : $cx($rowRightCol($ri));
            $endX = $ltr ? $cx($rowRightCol($ri)) : $cx($rowLeftCol($ri));

            $turnX = $endX;
            if ($ri < $rowCount - 1 && $rows[$ri + 1]->seats->count() > 0) {
                $nextStartCol = $ltr ? $rowRightCol($ri + 1) : $rowLeftCol($ri + 1);
                $turnX = $ltr
                    ? max($endX, $cx($nextStartCol))
                    : min($endX, $cx($nextStartCol));
            }
            $d .= sprintf('%s %.1f %.1f L %.1f %.1f ', $d === '' ? 'M' : 'L', $startX, $y, $turnX, $y);

            if ($ri < $rowCount - 1) {
                $nextY = $cy($ri + 1);
                $nextLtr = ($ri + 1) % 2 === 0;
                $nextEntryX = $nextLtr ? $cx($rowLeftCol($ri + 1)) : $cx($rowRightCol($ri + 1));
                $dir = $ltr ? 1 : -1;
                $outX = $turnX + $dir * $turn;
                $sweep = $ltr ? 1 : 0;
                $d .= sprintf('L %.1f %.1f A %.1f %.1f 0 0 %d %.1f %.1f L %.1f %.1f A %.1f %.1f 0 0 %d %.1f %.1f L %.1f %.1f ',
                    $outX - $dir * $cr, $y, $cr, $cr, $sweep, $outX, $y + $cr,
                    $outX, $nextY - $cr, $cr, $cr, $sweep, $outX - $dir * $cr, $nextY, $nextEntryX, $nextY);
                $flatX = $outX - $dir * $cr - $dir * 6;
                $turnArrows[] = [$flatX, $y, $ltr ? 'right' : 'left'];
                $turnArrows[] = [$flatX, $nextY, $ltr ? 'left' : 'right'];
            }
        }

        $endStubX = null;
        $lastRi = $rowCount - 1;
        if ($lastRi >= 0 && $rows[$lastRi]->seats->count() > 0) {
            $endDir = $lastRi % 2 === 0 ? 1 : -1;
            $rowEndX = $lastRi % 2 === 0 ? $cx($rowRightCol($lastRi)) : $cx($rowLeftCol($lastRi));
            $endStubX = $rowEndX + $endDir * ($box / 2 + 24);
            $d .= sprintf('L %.1f %.1f ', $endStubX, $cy($lastRi));
        }
        $path = $d === '' ? '' : sprintf('<path d="%s" fill="none" stroke="#000000" stroke-width="4"/>', trim($d));

        $arrows = '';
        if ($rowCount > 0 && $rows[0]->seats->count() > 0) {
            $sx = $cx($rowOffset(0));
            $labelY = $cy(0) - $box / 2 - 26;
            $arrows .= $this->svg->centeredLabel($mpdf, 'START', SvgUtilities::SIZE_START_LABEL, '#000000', $sx, $labelY, $stride * 3);
            $arrows .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#000000" stroke-width="4"/>', $sx, $labelY + 6, $sx, $cy(0) - $box / 2 + 2);
            $arrows .= $this->svg->arrowHead($sx, $cy(0) - $box / 2 + 4, 'down');
        }
        foreach ($rows as $ri => $row) {
            $count = $row->seats->count();
            if ($count < 2) {
                continue;
            }
            $ltr = $ri % 2 === 0;
            $g = (int) floor(($count - 1) / 2);
            $gapX = $cx($rowLeftCol($ri) + $g) + $stride / 2;
            $arrows .= $this->svg->arrowHead($gapX, $cy($ri), $ltr ? 'right' : 'left');
        }
        foreach ($turnArrows as [$tx, $ty, $tdir]) {
            $arrows .= $this->svg->arrowHead($tx, $ty, $tdir);
        }
        if ($endStubX !== null) {
            $arrows .= $this->svg->arrowHead($endStubX, $cy($lastRi), $lastRi % 2 === 0 ? 'right' : 'left');
        }

        $maxW = 195;
        $maxH = 105;
        $scale = min($maxW / $width, $maxH / $height);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%smm" height="%smm">%s%s%s%s</svg>',
            $width, $height, round($width * $scale, 1), round($height * $scale, 1), $under, $path, $over, $arrows
        );
    }
}
