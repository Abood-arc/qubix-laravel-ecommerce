<?php

use DigitalLabs\Shop\Enums\StoreLookEnum;
use DigitalLabs\Shop\StoreFonts;

use function Pest\Laravel\get;

/*
 * The storefront's fonts come from this site (config shop.fonts, public/themes/shop/default/fonts): the
 * store look's families, the template's own Poppins and DM Serif Display, and Noto Sans Arabic only on
 * right-to-left pages. Nothing is fetched from Google.
 */

beforeEach(function () {
    config(['responsecache.enabled' => false]);
});

$families = fn (StoreFonts $fonts) => array_values(array_unique(array_column($fonts->faces(), 'family')));

$fontPath = fn (string $url) => public_path(ltrim(parse_url($url, PHP_URL_PATH), '/'));

it('declares the look\'s families and the template\'s own, and Noto Sans Arabic only right to left', function (StoreLookEnum $look, array $lookFamilies) use ($families) {
    expect($families(new StoreFonts($look, rtl: false)))
        ->toEqualCanonicalizing([...$lookFamilies, 'Poppins', 'DM Serif Display']);

    expect($families(new StoreFonts($look, rtl: true)))
        ->toEqualCanonicalizing([...$lookFamilies, 'Poppins', 'DM Serif Display', 'Noto Sans Arabic']);
})->with([
    'Forest Green' => [StoreLookEnum::FOREST_GREEN, ['Karla']],
    'Marigold & Indigo' => [StoreLookEnum::MARIGOLD_INDIGO, ['Mukta', 'Baloo 2']],
    'Sand & Charcoal' => [StoreLookEnum::SAND_CHARCOAL, ['Archivo']],
    'Minimal White' => [StoreLookEnum::MINIMAL_WHITE, ['Manrope']],
]);

it('has a file for every face it declares', function (StoreLookEnum $look) use ($fontPath) {
    foreach ((new StoreFonts($look, rtl: true))->faces() as $face) {
        expect(file_exists($fontPath($face['url'])))->toBeTrue("Missing {$face['url']}");
    }
})->with(StoreLookEnum::cases());

it('preloads the look\'s main faces, or the Arabic one on a right-to-left page', function (StoreLookEnum $look, array $files) {
    $preloads = array_map(fn (string $url) => basename($url), (new StoreFonts($look, rtl: false))->preloads());

    expect($preloads)->toBe($files);

    expect(array_map(fn (string $url) => basename($url), (new StoreFonts($look, rtl: true))->preloads()))
        ->toBe(['noto-sans-arabic-arabic.woff2']);
})->with([
    'Forest Green' => [StoreLookEnum::FOREST_GREEN, ['karla-latin.woff2']],
    'Marigold & Indigo' => [StoreLookEnum::MARIGOLD_INDIGO, ['mukta-latin-400.woff2', 'baloo-2-latin.woff2']],
    'Sand & Charcoal' => [StoreLookEnum::SAND_CHARCOAL, ['archivo-latin.woff2']],
    'Minimal White' => [StoreLookEnum::MINIMAL_WHITE, ['manrope-latin.woff2']],
]);

it('gives a variable font one face per subset, with its weight range', function () {
    $karla = array_values(array_filter(
        (new StoreFonts(StoreLookEnum::FOREST_GREEN, rtl: false))->faces(),
        fn (array $face) => $face['family'] === 'Karla',
    ));

    expect(array_column($karla, 'weight'))->toBe(['400 800', '400 800'])
        ->and(array_map('basename', array_column($karla, 'url')))->toBe(['karla-latin.woff2', 'karla-latin-ext.woff2'])
        ->and($karla[1]['unicode_range'])->toContain('U+20AD-20C4');
});

it('writes the look\'s fonts into the storefront page, with no Google Fonts', function () {
    $this->pinStoreConfig(StoreLookEnum::CONFIG_KEY, StoreLookEnum::MINIMAL_WHITE->value);

    get(route('shop.home.index'))
        ->assertOk()
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('fonts.gstatic.com', false)
        ->assertSee('@font-face{font-family:"Manrope"', false)
        ->assertSee('@font-face{font-family:"Poppins"', false)
        ->assertDontSee('font-family:"Karla"', false)
        ->assertDontSee('font-family:"Noto Sans Arabic"', false)
        ->assertSee('href="'.asset('themes/shop/default/fonts/manrope/manrope-latin.woff2').'"', false)
        ->assertSee('as="font"', false);
});
