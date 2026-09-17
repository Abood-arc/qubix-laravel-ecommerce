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
