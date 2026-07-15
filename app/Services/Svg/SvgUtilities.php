<?php

namespace App\Services\Svg;

use Mpdf\Mpdf;

class SvgUtilities
{
    public const FONT_FAMILY = '';

    public const FONT_STYLE = '';

    public const SIZE_SEAT_LABEL = 30;      // order-card seat letters
    public const SIZE_START_LABEL = 22;     // order-card START marker
    public const SIZE_STAGE_LABEL = 24;     // master-overview stage cell
    public const SIZE_BLOCK_LABEL = 20;     // master-overview block name

    public const BASELINE_FACTOR = 0.35;

    public function centeredLabel(Mpdf $mpdf, string $text, float $preferredSize, string $color, float $centerX, float $baselineY, float $maxWidth): string
    {
        $size = $preferredSize;
        $width = $this->textWidth($mpdf, $text, $size);
        if ($width > $maxWidth && $width > 0) {
            $size = max($preferredSize * ($maxWidth / $width), 6.0);
            $width = $this->textWidth($mpdf, $text, $size);
        }

        $family = self::FONT_FAMILY !== '' ? sprintf(' font-family="%s"', self::FONT_FAMILY) : '';

        return sprintf(
            '<text x="%.1f" y="%.1f" fill="%s" font-size="%.1f"%s text-anchor="start">%s</text>',
            $centerX - $width / 2, $baselineY, $color, $size, $family, e($text)
        );
    }

    public function textWidth(Mpdf $mpdf, string $text, float $fontSize): float
    {
        [$f, $s, $sz] = [$mpdf->FontFamily, $mpdf->FontStyle, $mpdf->FontSizePt];
        $mpdf->SetFont(self::FONT_FAMILY ?: $f, self::FONT_STYLE, $fontSize, false);
        $widthMm = $mpdf->GetStringWidth($text);
        $mpdf->SetFont($f, $s, $sz, false);

        return $widthMm * 72 / 25.4;
    }

    public function arrowHead(float $x, float $y, string $direction): string
    {
        $s = 14;
        [$tip, $a, $b] = match ($direction) {
            'right' => [[$x + $s, $y], [$x - $s * 0.6, $y - $s], [$x - $s * 0.6, $y + $s]],
            'left' => [[$x - $s, $y], [$x + $s * 0.6, $y - $s], [$x + $s * 0.6, $y + $s]],
            'down' => [[$x, $y + $s], [$x - $s, $y - $s * 0.6], [$x + $s, $y - $s * 0.6]],
            'up' => [[$x, $y - $s], [$x - $s, $y + $s * 0.6], [$x + $s, $y + $s * 0.6]],
        };

        return sprintf(
            '<polygon points="%.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="#000000" stroke="#ffffff" stroke-width="1.5"/>',
            $tip[0], $tip[1], $a[0], $a[1], $b[0], $b[1]
        );
    }
}
