<?php

namespace DigitalLabs\Core\Helpers;

class BrandPalette
{
    const DEFAULT_PRIMARY = '#1f5f4f';

    const BORDER_LIGHTNESS_FACTOR = 1.26;

    const SCROLLED_LIGHTNESS_FACTOR = 0.45;

    const MIN_LIGHTNESS_DELTA = 4;

    const WHITE = '#ffffff';

    const DARK_FOREGROUND = '#1a1a1a';

    /**
     * Fixed saturation/lightness for the footer's pale tint, calibrated
     * against the current hardcoded footer colors (#F1EADF / #e9decc).
     * Only the hue is derived from the primary color — lightness and
     * saturation stay constant so the footer reads as a pale surface
     * regardless of how light or dark the chosen primary is.
     */
    const FOOTER_BG_SATURATION = 39;

    const FOOTER_BG_LIGHTNESS = 91;

    const FOOTER_BORDER_SATURATION = 40;

    const FOOTER_BORDER_LIGHTNESS = 86;

    /**
     * Derive the full brand token set from one primary hex color.
     * Malformed input falls back to the default primary rather than
     * letting an exception reach a page render.
     */
    public static function derive(?string $hex): array
    {
        $rgb = static::parseHex($hex) ?? static::parseHex(static::DEFAULT_PRIMARY);

        [$h, $s, $l] = static::rgbToHsl($rgb);

        $borderL = min(92, $l * static::BORDER_LIGHTNESS_FACTOR);
        if ($borderL - $l < static::MIN_LIGHTNESS_DELTA) {
            $borderL = min(98, $l + static::MIN_LIGHTNESS_DELTA);
        }

        $scrolledL = max(2, $l * static::SCROLLED_LIGHTNESS_FACTOR);
        if ($l - $scrolledL < static::MIN_LIGHTNESS_DELTA) {
            $scrolledL = max(0, $l - static::MIN_LIGHTNESS_DELTA);
        }

        return [
            'primary' => static::rgbToHex($rgb),
            'border' => static::rgbToHex(static::hslToRgb($h, $s, $borderL)),
            'scrolled' => static::rgbToHex(static::hslToRgb($h, $s, $scrolledL)),
            'onPrimary' => static::readableForeground($rgb),
            'footerBg' => static::rgbToHex(static::hslToRgb($h, static::FOOTER_BG_SATURATION, static::FOOTER_BG_LIGHTNESS)),
            'footerBorder' => static::rgbToHex(static::hslToRgb($h, static::FOOTER_BORDER_SATURATION, static::FOOTER_BORDER_LIGHTNESS)),
        ];
    }

    /**
     * Parses a 6-digit hex color (with or without a leading #) into an
     * [r, g, b] triple, or null if the input isn't a valid 6-digit hex color.
     */
    protected static function parseHex(?string $hex): ?array
    {
        if ($hex === null) {
            return null;
        }

        $hex = ltrim(trim($hex), '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Picks white or a near-black foreground, whichever gives the higher
     * WCAG contrast ratio against the given background color.
     */
    protected static function readableForeground(array $rgb): string
    {
        $backgroundLuminance = static::relativeLuminance($rgb);

        $contrastWithWhite = static::contrastRatio($backgroundLuminance, 1.0);
        $contrastWithBlack = static::contrastRatio($backgroundLuminance, 0.0);

        return $contrastWithWhite >= $contrastWithBlack
            ? static::WHITE
            : static::DARK_FOREGROUND;
    }

    protected static function contrastRatio(float $luminanceA, float $luminanceB): float
    {
        $lighter = max($luminanceA, $luminanceB);
        $darker = min($luminanceA, $luminanceB);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * WCAG relative luminance, https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
     */
    protected static function relativeLuminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(function ($channel) {
            $channel /= 255;

            return $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * @return array{0: float, 1: float, 2: float} [hue 0-360, saturation 0-100, lightness 0-100]
     */
    protected static function rgbToHsl(array $rgb): array
    {
        [$r, $g, $b] = array_map(fn ($c) => $c / 255, $rgb);

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        $l = ($max + $min) / 2;

        if ($delta == 0) {
            return [0.0, 0.0, $l * 100];
        }

        $s = $delta / (1 - abs(2 * $l - 1));

        $h = match ($max) {
            $r => 60 * fmod((($g - $b) / $delta), 6),
            $g => 60 * ((($b - $r) / $delta) + 2),
            default => 60 * ((($r - $g) / $delta) + 4),
        };

        if ($h < 0) {
            $h += 360;
        }

        return [$h, $s * 100, $l * 100];
    }

    /**
     * @param  float  $h  0-360
     * @param  float  $s  0-100
     * @param  float  $l  0-100
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function hslToRgb(float $h, float $s, float $l): array
    {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ];
    }

    protected static function rgbToHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }
}
