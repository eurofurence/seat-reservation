<?php

namespace App\Services\Svg;

use Mpdf\Mpdf;

class SvgUtilities
{
    public const FONT_FAMILY = '';

    public const FONT_STYLE = '';

    public const SIZE_SEAT_LABEL = 30; // order-card

    public const SIZE_START_LABEL = 22; // order-card

    public const SIZE_STAGE_LABEL = 24; // master-card

    public const SIZE_BLOCK_LABEL = 20; // master-card

    public const BASELINE_FACTOR = 0.35;

    private const STROKE = '#000000';

    public function centeredLabelX(Mpdf $mpdf, string $text, float $preferredSize, string $color, float $centerX, float $baselineY, float $maxWidth): string
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
        [$family, $style, $sizePt] = [$mpdf->FontFamily, $mpdf->FontStyle, $mpdf->FontSizePt];

        $mpdf->SetFont(self::FONT_FAMILY ?: $family, self::FONT_STYLE, $fontSize, false);
        $widthMm = $mpdf->GetStringWidth($text);
        $mpdf->SetFont($family, $style, $sizePt, false);

        return $widthMm * 72 / 25.4;
    }

    public function document(float $viewW, float $viewH, float $widthMm, float $heightMm, string $body): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%smm" height="%smm">%s</svg>',
            $viewW, $viewH, round($widthMm, 1), round($heightMm, 1), $body
        );
    }

    public function rect(float $x, float $y, float $w, float $h, string $fill, float $strokeWidth, float $rx = 0): string
    {
        $corners = $rx > 0 ? sprintf(' rx="%.1f" ry="%.1f"', $rx, $rx) : '';

        return sprintf(
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f"%s fill="%s" stroke="%s" stroke-width="%.1f"/>',
            $x, $y, $w, $h, $corners, $fill, self::STROKE, $strokeWidth
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $strokeWidth): string
    {
        return sprintf(
            '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="%.1f"/>',
            $x1, $y1, $x2, $y2, self::STROKE, $strokeWidth
        );
    }

    public function path(string $d, float $strokeWidth): string
    {
        return sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="%.1f"/>', $d, self::STROKE, $strokeWidth);
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
