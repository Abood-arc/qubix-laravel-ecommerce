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

it('derives onPrimaryRgb as the space-separated RGB triplet of onPrimary, for Tailwind opacity composition', function () {
    // Arrange: a dark primary, so onPrimary is white (#ffffff -> "255 255 255").
    $darkPalette = BrandPalette::derive('#1f5f4f');

    // Arrange: a pale primary, so onPrimary is the dark foreground (#1a1a1a -> "26 26 26").
    $palePalette = BrandPalette::derive('#FFE08A');

    // Act and Assert.
    expect($darkPalette['onPrimaryRgb'])->toBe('255 255 255');
    expect($palePalette['onPrimaryRgb'])->toBe('26 26 26');
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

it('makes the footer the same colour as the header: background is the primary, border is the header border', function ($hex) {
    // Owner decision (2026-09-26): the footer reads as the same brand colour as the header,
    // replacing the earlier "pale tint of the brand hue" (Task 3.3 Step 1, option a).
    // Act.
    $palette = BrandPalette::derive($hex);

    // Assert.
    expect($palette['footerBg'])->toBe($palette['primary']);
    expect($palette['footerBorder'])->toBe($palette['border']);
})->with(['#1f5f4f', '#C2410C', '#101014', '#FFE08A']);

it('gives the footer readable text through the same on-primary colour as the header, for light and dark brands', function () {
    // Assert: the footer text uses onPrimary, so it must contrast with footerBg (== primary).
    expect(BrandPalette::derive('#FFE08A')['onPrimary'])->toBe(BrandPalette::DARK_FOREGROUND);
    expect(BrandPalette::derive('#101014')['onPrimary'])->toBe(BrandPalette::WHITE);
    expect(BrandPalette::derive('#C2410C')['onPrimary'])->toBe(BrandPalette::WHITE);
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
