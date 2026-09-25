<?php

use Spatie\ResponseCache\Facades\ResponseCache;

use function Pest\Laravel\get;

/**
 * The storefront layout used to hardcode one third-party chatbot <script> (a Zanderio widget id
 * belonging to the first client, JJ Bags), so every store built from this code — including each
 * new fleet client — showed another client's chatbot and sent its visitors to a third party.
 * It is now opt-in per deployment via SHOP_CHATBOT_WIDGET_ID.
 */
it('does not load any third-party chatbot widget by default', function () {
    ResponseCache::clear();
    config(['shop.chatbot.widget_id' => '']);

    $content = get(route('shop.home.index'))->assertOk()->content();

    expect($content)->not->toContain('zanderio')
        ->and($content)->not->toContain('widget/loader.js');
});

it('loads the configured chatbot widget when a widget id is set', function () {
    ResponseCache::clear();
    config(['shop.chatbot.widget_id' => 'wdg_TestWidget123']);

    $content = get(route('shop.home.index'))->assertOk()->content();

    expect($content)->toContain('data-id="wdg_TestWidget123"')
        ->and($content)->toContain('widget/loader.js');
});

it('escapes the configured widget id', function () {
    ResponseCache::clear();
    config(['shop.chatbot.widget_id' => '"><script>alert(1)</script>']);

    $content = get(route('shop.home.index'))->assertOk()->content();

    expect($content)->not->toContain('"><script>alert(1)</script>');
});
