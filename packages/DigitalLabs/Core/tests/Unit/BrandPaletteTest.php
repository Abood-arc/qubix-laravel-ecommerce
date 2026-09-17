<?php

use DigitalLabs\Core\Helpers\BrandPalette;

function hexToRgb(string $hex): array
{
    $hex = ltrim($hex, '#');

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function hexLightness(string $hex): float
{
    [$r, $g, $b] = array_map(fn ($c) => $c / 255, hexToRgb($hex));

    return (max($r, $g, $b) + min($r, $g, $b)) / 2 * 100;
}

function assertHexClose(string $actual, string $expected, int $tolerancePerChannel = 10): void
{
    $actualRgb = hexToRgb($actual);
    $expectedRgb = hexToRgb($expected);

    foreach ($actualRgb as $i => $channel) {
        expect(abs($channel - $expectedRgb[$i]))
            ->toBeLessThanOrEqual($tolerancePerChannel);
    }
}

function hexHue(string $hex): float
{
    [$r, $g, $b] = array_map(fn ($c) => $c / 255, hexToRgb($hex));

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $delta = $max - $min;

    if ($delta == 0) {
        return 0.0;
    }

    $h = match ($max) {
        $r => 60 * fmod((($g - $b) / $delta), 6),
        $g => 60 * ((($b - $r) / $delta) + 2),
        default => 60 * ((($r - $g) / $delta) + 4),
    };

    return $h < 0 ? $h + 360 : $h;
}

it('derives border and scrolled shades close to the current hardcoded palette for the calibration primary', function () {
    // Arrange.
    $hex = '#1f5f4f';

    // Act.
    $palette = BrandPalette::derive($hex);

    // Assert.
    expect($palette['primary'])->toBe('#1f5f4f');
    assertHexClose($palette['border'], '#2f6f60');
    assertHexClose($palette['scrolled'], '#0d2b1e');
    expect($palette['onPrimary'])->toBe('#ffffff');
});

it('chooses a dark foreground for a pale primary color', function () {
    // Arrange.
    $hex = '#FFE08A';

    // Act.
    $palette = BrandPalette::derive($hex);

    // Assert.
    expect($palette['onPrimary'])->not->toBe('#ffffff');
    expect(hexLightness($palette['onPrimary']))->toBeLessThan(30);
});

it('keeps the scrolled shade distinguishable from a very dark primary color', function () {
    // Arrange.
    $hex = '#101014';

    // Act.
    $palette = BrandPalette::derive($hex);

    // Assert.
    expect($palette['scrolled'])->not->toBe($palette['primary']);
    expect(hexLightness($palette['primary']) - hexLightness($palette['scrolled']))
        ->toBeGreaterThanOrEqual(2);
});

it('derives a footer background and border that follow the primary hue, not a fixed cream hue', function () {
    // Arrange.
    $hex = '#1f5f4f'; // hue 165°, far from the old hardcoded cream hue (~37°).

    // Act.
    $palette = BrandPalette::derive($hex);

    // Assert.
    expect(abs(hexHue($palette['footerBg']) - 165))->toBeLessThanOrEqual(5);
    expect(abs(hexHue($palette['footerBorder']) - 165))->toBeLessThanOrEqual(5);
});

it('keeps the footer background light and the border slightly darker, regardless of primary lightness', function () {
    // Arrange.
    $hex = '#101014'; // a very dark primary.

    // Act.
    $palette = BrandPalette::derive($hex);

    // Assert.
    expect(hexLightness($palette['footerBg']))->toBeGreaterThan(80);
    expect(hexLightness($palette['footerBorder']))->toBeGreaterThan(75);
    expect(hexLightness($palette['footerBg']))->toBeGreaterThan(hexLightness($palette['footerBorder']));
});

it('falls back to the default palette for malformed input', function ($malformed) {
    // Arrange.
    $default = BrandPalette::derive('#1f5f4f');

    // Act.
    $palette = BrandPalette::derive($malformed);

    // Assert.
    expect($palette)->toBe($default);
})->with([
    'null' => [null],
    'empty string' => [''],
    'not a hex color' => ['not-a-color'],
    'wrong length' => ['#1f5f'],
    'missing hash and invalid chars' => ['zzzzzz'],
]);
