<?php

namespace App\Services\Svg;

use App\Models\Block;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;

class OrderCardSvgGenerator
{
    private const BOX = 64;

    private const GAP = 12;

    private const PAD = 46;

    private const TURN = 80;        // reach of the rounded u turns past a row edge

    private const RADIUS = 12;      // seat-box corner radius

    private const CORNER = 18;      // u turn corner radius

    private const STRIDE = self::BOX + self::GAP;

    /** @var Collection Rows of the block being rendered, sorted by order. */
    private Collection $rows;

    private int $maxSeats;

    private float $left;

    public function __construct(private SvgUtilities $svg) {}

    /**
     * Block preview for the order-card divider.
     *
     * @param  array<int,bool>  $bookedSeatIds  Set of booked seat IDs.
     */
    public function render(Block $block, array $bookedSeatIds, Mpdf $mpdf): string
    {
        $this->rows = $block->rows->sortBy('order')->values();
        if ($this->rows->isEmpty()) {
            return '';
        }

        $rowCount = $this->rows->count();
        $this->maxSeats = max((int) $this->rows->max(fn ($r) => $r->seats->count()), 1);
        $this->left = self::PAD + self::TURN;
        $width = $this->left * 2 + $this->maxSeats * self::STRIDE - self::GAP;
        $height = self::PAD * 2 + $rowCount * self::STRIDE - self::GAP;

        [$under, $over] = $this->seatBoxes($bookedSeatIds, $mpdf);
        [$path, $turnArrows] = $this->windingPath($rowCount);
        $arrows = $this->arrows($rowCount, $turnArrows, $mpdf);

        $scale = min(195 / $width, 125 / $height);

        return $this->svg->document($width, $height, $width * $scale, $height * $scale, $under.$path.$over.$arrows);
    }

    /** Seat center x for a column. */
    private function cx(float $col): float
    {
        return $this->left + $col * self::STRIDE + self::BOX / 2;
    }

    /** Seat center y for a row. */
    private function cy(int $ri): float
    {
        return self::PAD + $ri * self::STRIDE + self::BOX / 2;
    }

    private function rowOffset(int $ri): float
    {
        $slack = $this->maxSeats - $this->rows[$ri]->seats->count();

        return match ($slack > 0 ? ($this->rows[$ri]->alignment ?? 'center') : 'left') {
            'right' => (float) $slack,
            'center' => $slack / 2,
            default => 0.0,
        };
    }

    private function rowLeftCol(int $ri): float
    {
        return $this->rowOffset($ri);
    }

    private function rowRightCol(int $ri): float
    {
        return $this->rowOffset($ri) + $this->rows[$ri]->seats->count() - 1;
    }

    private function seatBoxes(array $bookedSeatIds, Mpdf $mpdf): array
    {
        $sz = SvgUtilities::SIZE_SEAT_LABEL;
        $under = $over = '';
        foreach ($this->rows as $ri => $row) {
            $off = $this->rowOffset($ri);
            foreach ($row->seats->sortBy('number')->values() as $col => $seat) {
                $x = $this->left + ($col + $off) * self::STRIDE;
                $y = self::PAD + $ri * self::STRIDE;
                $isBooked = isset($bookedSeatIds[$seat->id]);
                $box = $this->svg->rect($x, $y, self::BOX, self::BOX, $isBooked ? '#000000' : '#ffffff', 2, self::RADIUS)
                    .$this->svg->centeredLabelX(
                        $mpdf, (string) $seat->label, $sz, $isBooked ? '#ffffff' : '#000000',
                        $x + self::BOX / 2, $y + self::BOX / 2 + $sz * SvgUtilities::BASELINE_FACTOR, self::BOX - 8
                    );
                if ($isBooked) {
                    $over .= $box;
                } else {
                    $under .= $box;
                }
            }
        }

        return [$under, $over];
    }

    /**
     * @return array{0: string, 1: array<int,array{0: float, 1: float, 2: string}>}
     */
    private function windingPath(int $rowCount): array
    {
        $d = '';
        $turnArrows = [];
        for ($ri = 0; $ri < $rowCount; $ri++) {
            if ($this->rows[$ri]->seats->count() < 1) {
                continue;
            }
            $ltr = $ri % 2 === 0;
            $y = $this->cy($ri);
            $startX = $ltr ? $this->cx($this->rowLeftCol($ri)) : $this->cx($this->rowRightCol($ri));
            $endX = $ltr ? $this->cx($this->rowRightCol($ri)) : $this->cx($this->rowLeftCol($ri));

            $turnX = $endX;
            if ($ri < $rowCount - 1 && $this->rows[$ri + 1]->seats->count() > 0) {
                $nextStartCol = $ltr ? $this->rowRightCol($ri + 1) : $this->rowLeftCol($ri + 1);
                $turnX = $ltr ? max($endX, $this->cx($nextStartCol)) : min($endX, $this->cx($nextStartCol));
            }
            $d .= sprintf('%s %.1f %.1f L %.1f %.1f ', $d === '' ? 'M' : 'L', $startX, $y, $turnX, $y);

            if ($ri < $rowCount - 1) {
                $nextY = $this->cy($ri + 1);
                $nextLtr = ($ri + 1) % 2 === 0;
                $nextEntryX = $nextLtr ? $this->cx($this->rowLeftCol($ri + 1)) : $this->cx($this->rowRightCol($ri + 1));
                $dir = $ltr ? 1 : -1;
                $outX = $turnX + $dir * self::TURN;

                $d .= $this->uTurn($y, $nextEntryX, $nextY, $outX, $dir, $ltr);
                $flatX = $outX - $dir * self::CORNER - $dir * 6;
                $turnArrows[] = [$flatX, $y, $ltr ? 'right' : 'left'];
                $turnArrows[] = [$flatX, $nextY, $ltr ? 'left' : 'right'];
            }
        }

        $lastRi = $rowCount - 1;
        if ($lastRi >= 0 && $this->rows[$lastRi]->seats->count() > 0) {
            $endDir = $lastRi % 2 === 0 ? 1 : -1;
            $rowEndX = $lastRi % 2 === 0 ? $this->cx($this->rowRightCol($lastRi)) : $this->cx($this->rowLeftCol($lastRi));
            $endStubX = $rowEndX + $endDir * (self::BOX / 2 + 24);
            $d .= sprintf('L %.1f %.1f ', $endStubX, $this->cy($lastRi));
            $turnArrows[] = [$endStubX, $this->cy($lastRi), $lastRi % 2 === 0 ? 'right' : 'left'];
        }

        $path = $d === '' ? '' : $this->svg->path(trim($d), 4);

        return [$path, $turnArrows];
    }

    private function uTurn(float $y, float $nextEntryX, float $nextY, float $outX, int $dir, bool $ltr): string
    {
        $cr = self::CORNER;
        $sweep = $ltr ? 1 : 0;

        return sprintf('L %.1f %.1f A %.1f %.1f 0 0 %d %.1f %.1f L %.1f %.1f A %.1f %.1f 0 0 %d %.1f %.1f L %.1f %.1f ',
            $outX - $dir * $cr, $y, $cr, $cr, $sweep, $outX, $y + $cr,
            $outX, $nextY - $cr, $cr, $cr, $sweep, $outX - $dir * $cr, $nextY, $nextEntryX, $nextY);
    }

    /**
     * @param  array<int,array{0: float, 1: float, 2: string}>  $turnArrows
     */
    private function arrows(int $rowCount, array $turnArrows, Mpdf $mpdf): string
    {
        $arrows = '';
        if ($rowCount > 0 && $this->rows[0]->seats->count() > 0) {
            $sx = $this->cx($this->rowOffset(0));
            $labelY = $this->cy(0) - self::BOX / 2 - 26;
            $arrows .= $this->svg->centeredLabelX($mpdf, 'START', SvgUtilities::SIZE_START_LABEL, '#000000', $sx, $labelY, self::STRIDE * 3);
            $arrows .= $this->svg->line($sx, $labelY + 6, $sx, $this->cy(0) - self::BOX / 2 + 2, 4);
            $arrows .= $this->svg->arrowHead($sx, $this->cy(0) - self::BOX / 2 + 4, 'down');
        }
        foreach ($this->rows as $ri => $row) {
            $count = $row->seats->count();
            if ($count < 2) {
                continue;
            }
            $g = (int) floor(($count - 1) / 2);
            $gapX = $this->cx($this->rowLeftCol($ri) + $g) + self::STRIDE / 2;
            $arrows .= $this->svg->arrowHead($gapX, $this->cy($ri), $ri % 2 === 0 ? 'right' : 'left');
        }
        foreach ($turnArrows as [$tx, $ty, $tdir]) {
            $arrows .= $this->svg->arrowHead($tx, $ty, $tdir);
        }

        return $arrows;
    }
}
