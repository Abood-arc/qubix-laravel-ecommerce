<?php

use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * Configuration > General > Design > Product card: the card style for grids, chosen by clicking a live
 * preview of each style (a radio per style, no dropdown), and the unit of the size line. The Store look
 * is chosen the same way.
 */

it('offers the three card styles as radios, each with a live preview, after Store Look', function () {
    $this->loginAsAdmin();

    $preview = fn (string $style) => e(route('shop.card-preview', ['style' => $style, 'look' => 'forest-green']));

    get(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'design']))
        ->assertOk()
        ->assertSeeInOrder([
            trans('admin::app.configuration.index.general.design.store-look.title'),
            trans('admin::app.configuration.index.general.design.product-card.title'),
            'name="general[design][product_card][style]"',
            'value="classic"',
            $preview('classic'),
            'name="general[design][product_card][style]"',
            'value="swipe"',
            $preview('swipe'),
            'name="general[design][product_card][style]"',
            'value="deck"',
            $preview('deck'),
        ], false)
        ->assertDontSee('<select name="general[design][product_card][style]"', false)
        ->assertSee('name="general[design][product_card][size_unit]"', false);
});

it('offers the four store looks as radios, each previewed with the saved card style and the store header', function () {
    $this->loginAsAdmin();

    $html = get(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'design']))
        ->assertOk()
        ->getContent();

    foreach (['forest-green', 'marigold-indigo', 'sand-charcoal', 'minimal-white'] as $look) {
        expect($html)
            ->toMatch('/name="general\[design\]\[store_look\]\[look\]"\s+value="'.$look.'"/')
            ->toContain(e(route('shop.card-preview', ['style' => 'classic', 'look' => $look, 'header' => 1])));
    }

    expect(substr_count($html, 'sandbox="allow-scripts allow-same-origin"'))->toBe(7)
        ->and(substr_count($html, 'loading="lazy"'))->toBeGreaterThanOrEqual(7);
});

it('saves the chosen style and size unit for the channel', function () {
    $this->loginAsAdmin();

    $channel = core()->getDefaultChannelCode();

    post(route('admin.configuration.store', ['slug' => 'general', 'slug2' => 'design']), [
        'channel' => $channel,
        'locale' => 'en',
        'general' => [
            'design' => [
                'product_card' => [
                    'style' => 'swipe',
                    'size_unit' => 'cm',
                ],
            ],
        ],
    ])->assertRedirect();

    $saved = fn (string $code) => DB::table('core_config')
        ->where('code', $code)
        ->where('channel_code', $channel)
        ->value('value');

    expect($saved('general.design.product_card.style'))->toBe('swipe')
        ->and($saved('general.design.product_card.size_unit'))->toBe('cm');
});

it('names and describes each option in the admin language', function () {
    $this->loginAsAdmin();

    app()->setLocale('ar');

    get(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'design']))
        ->assertOk()
        ->assertSeeInOrder(['رزمة صور', 'الصور في كومة صغيرة، مع زر دائري للإضافة إلى السلة تحتها.'])
        ->assertSeeInOrder(['ذهبي ونيلي', 'رأس أصفر ساطع ونص نيلي.'])
        ->assertSee('title="معاينة حية: رزمة صور"', false);
});
