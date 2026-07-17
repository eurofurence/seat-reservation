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

    private const START_STEM = 52;  // box edge to label: arrow gap + stem + a little

    private const STRIDE = self::BOX + self::GAP;

    /** @var Collection Rows of the block being rendered, sorted by order. */
    private Collection $rows;

    private int $maxSeats;

    private int $rowCount;

    private int $rotation = 0;

    private float $left;

    private float $seatSpan;

    private float $rowSpan;

    private float $rowTop;

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

        $this->rotation = (int) ($block->rotation ?? 0);
        $this->rowCount = $this->rows->count();
        $this->maxSeats = max((int) $this->rows->max(fn ($r) => $r->seats->count()), 1);
        $this->left = self::PAD + self::TURN;

        $this->rowTop = match ($this->rotation) {
            0 => self::PAD,
            180 => self::PAD + self::START_STEM,
            default => self::PAD + self::START_STEM + $this->svg->textWidth($mpdf, 'START', SvgUtilities::SIZE_START_LABEL),
        };

        $this->seatSpan = $this->left * 2 + $this->maxSeats * self::STRIDE - self::GAP;
        $this->rowSpan = $this->rowTop + self::PAD + $this->rowCount * self::STRIDE - self::GAP;

        $vertical = $this->rotation === 90 || $this->rotation === 270;
        $width = $vertical ? $this->rowSpan : $this->seatSpan;
        $height = $vertical ? $this->seatSpan : $this->rowSpan;

        $boxes = $this->seatBoxes($bookedSeatIds, $mpdf);
        [$path, $turnArrows] = $this->windingPath();
        $arrows = $this->arrows($turnArrows, $mpdf);

        $scale = min(195 / $width, 105 / $height);

        return $this->svg->document($width, $height, $width * $scale, $height * $scale, $path.$boxes.$arrows);
    }

    /** Seat center x for a column. */
    private function cx(float $col): float
    {
        return $this->left + $col * self::STRIDE + self::BOX / 2;
    }

    /** Seat center y for a row. */
    private function cy(float $ri): float
    {
        return $this->rowTop + $ri * self::STRIDE + self::BOX / 2;
    }

    private function place(float $x, float $y): array
    {
        return match ($this->rotation) {
            90 => [$this->rowSpan - $y, $x],
            180 => [$this->seatSpan - $x, $this->rowSpan - $y],
            270 => [$y, $this->seatSpan - $x],
            default => [$x, $y],
        };
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

    private function seatBoxes(array $bookedSeatIds, Mpdf $mpdf): string
    {
        $sz = SvgUtilities::SIZE_SEAT_LABEL;
        $boxes = '';
        foreach ($this->rows as $ri => $row) {
            $off = $this->rowOffset($ri);
            foreach ($row->seats->sortBy('number')->values() as $col => $seat) {
                [$cxp, $cyp] = $this->place($this->cx($col + $off), $this->cy($ri));
                $x = $cxp - self::BOX / 2;
                $y = $cyp - self::BOX / 2;
                $isBooked = isset($bookedSeatIds[$seat->id]);
                $boxes .= $this->svg->rect($x, $y, self::BOX, self::BOX, $isBooked ? '#000000' : '#ffffff', 2, self::RADIUS)
                    .$this->svg->centeredLabelX(
                        $mpdf, (string) $seat->label, $sz, $isBooked ? '#ffffff' : '#000000',
                        $cxp, $cyp + $sz * SvgUtilities::BASELINE_FACTOR, self::BOX - 8
                    );
            }
        }

        return $boxes;
    }

    /**
     * @return array{0: string, 1: array<int,array{0: float, 1: float, 2: string}>}
     */
    private function windingPath(): array
    {
        $rowCount = $this->rowCount;
        $moves = [];        // ['L', x, y] or ['A', rx, ry, sweep, x, y]
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
            $moves[] = ['L', $startX, $y];
            $moves[] = ['L', $turnX, $y];

            if ($ri < $rowCount - 1) {
                $nextY = $this->cy($ri + 1);
                $nextLtr = ($ri + 1) % 2 === 0;
                $nextEntryX = $nextLtr ? $this->cx($this->rowLeftCol($ri + 1)) : $this->cx($this->rowRightCol($ri + 1));
                $dir = $ltr ? 1 : -1;
                $outX = $turnX + $dir * self::TURN;

                array_push($moves, ...$this->uTurn($y, $nextEntryX, $nextY, $outX, $dir, $ltr));
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
            $moves[] = ['L', $endStubX, $this->cy($lastRi)];
            $turnArrows[] = [$endStubX, $this->cy($lastRi), $lastRi % 2 === 0 ? 'right' : 'left'];
        }

        $path = empty($moves) ? '' : $this->svg->path($this->rotatedPathData($moves), 4);

        return [$path, $turnArrows];
    }

    /**
     * @return array<int,array<int|float|string>>
     */
    private function uTurn(float $y, float $nextEntryX, float $nextY, float $outX, int $dir, bool $ltr): array
    {
        $cr = self::CORNER;
        $sweep = $ltr ? 1 : 0;

        return [
            ['L', $outX - $dir * $cr, $y],
            ['A', $cr, $cr, $sweep, $outX, $y + $cr],
            ['L', $outX, $nextY - $cr],
            ['A', $cr, $cr, $sweep, $outX - $dir * $cr, $nextY],
            ['L', $nextEntryX, $nextY],
        ];
    }

    /**
     * @param  array<int,array<int|float|string>>  $moves
     */
    private function rotatedPathData(array $moves): string
    {
        $d = '';
        foreach ($moves as $i => $m) {
            if ($m[0] === 'A') {
                [, $rx, $ry, $sweep, $x, $y] = $m;
                [$px, $py] = $this->place($x, $y);
                $d .= sprintf('A %.1f %.1f 0 0 %d %.1f %.1f ', $rx, $ry, $sweep, $px, $py);
            } else {
                [, $x, $y] = $m;
                [$px, $py] = $this->place($x, $y);
                $d .= sprintf('%s %.1f %.1f ', $i === 0 ? 'M' : 'L', $px, $py);
            }
        }

        return trim($d);
    }

    /**
     * @param  array<int,array{0: float, 1: float, 2: string}>  $turnArrows
     */
    private function arrows(array $turnArrows, Mpdf $mpdf): string
    {
        $arrows = $this->startMarker($mpdf);

        foreach ($this->rows as $ri => $row) {
            $count = $row->seats->count();
            if ($count < 2) {
                continue;
            }
            $g = (int) floor(($count - 1) / 2);
            $gapX = $this->cx($this->rowLeftCol($ri) + $g) + self::STRIDE / 2;
            [$mx, $my] = $this->place($gapX, $this->cy($ri));
            $arrows .= $this->svg->arrowHead($mx, $my, $this->dir($ri % 2 === 0 ? 'right' : 'left'));
        }
        foreach ($turnArrows as [$tx, $ty, $tdir]) {
            [$px, $py] = $this->place($tx, $ty);
            $arrows .= $this->svg->arrowHead($px, $py, $this->dir($tdir));
        }

        return $arrows;
    }

    private function startMarker(Mpdf $mpdf): string
    {
        if ($this->rowCount === 0 || $this->rows[0]->seats->count() === 0) {
            return '';
        }

        [$bx, $byc] = $this->place($this->cx($this->rowOffset(0)), $this->cy(0));
        $entry = $this->dir('down');
        [$ox, $oy] = match ($entry) {
            'down' => [0, -1],
            'up' => [0, 1],
            'left' => [1, 0],
            'right' => [-1, 0],
        };
        $edge = self::BOX / 2;
        $sz = SvgUtilities::SIZE_START_LABEL;
        $tipX = $bx + $ox * ($edge + 8);
        $tipY = $byc + $oy * ($edge + 8);
        $stemX = $bx + $ox * ($edge + 30);
        $stemY = $byc + $oy * ($edge + 30);

        $labelOut = $edge + 38 + ($ox !== 0 ? $this->svg->textWidth($mpdf, 'START', $sz) / 2 : 0);
        $drop = $this->rotation === 180 ? 12 : 0;
        $labelX = $bx + $ox * $labelOut;
        $labelY = $byc + $oy * $labelOut + $sz * SvgUtilities::BASELINE_FACTOR + $drop;

        return $this->svg->line($stemX, $stemY, $tipX, $tipY, 4)
            .$this->svg->arrowHead($tipX, $tipY, $entry)
            .$this->svg->centeredLabelX($mpdf, 'START', $sz, '#000000', $labelX, $labelY, self::STRIDE * 3);
    }

    private function dir(string $direction): string
    {
        if ($this->rotation === 0) {
            return $direction;
        }
        $ring = ['right', 'down', 'left', 'up'];
        $steps = intdiv($this->rotation, 90);
        $i = array_search($direction, $ring, true);

        return $ring[($i + $steps) % 4];
    }
}
