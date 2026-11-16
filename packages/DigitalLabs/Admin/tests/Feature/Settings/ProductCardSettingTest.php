<?php

use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * Configuration > General > Design > Product card: the card style for grids, with a
 * preview of each style, and the unit of the size line.
 */

it('offers the three card styles with a preview of each, after Store Look', function () {
    $this->loginAsAdmin();

    get(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'design']))
        ->assertOk()
        ->assertSeeInOrder([
            trans('admin::app.configuration.index.general.design.store-look.title'),
            trans('admin::app.configuration.index.general.design.product-card.title'),
            'data-card-preview="classic"',
            'data-card-preview="swipe"',
            'data-card-preview="deck"',
        ], false)
        ->assertSee('name="general[design][product_card][style]"', false)
        ->assertSee('name="general[design][product_card][size_unit]"', false);
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
