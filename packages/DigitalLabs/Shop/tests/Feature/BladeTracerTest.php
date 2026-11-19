<?php

use function Pest\Laravel\get;

/*
 * The Blade tracer outlines every view for debugging. With it switched off (config view.tracer, the
 * default) its styles and its once-a-second script must not reach shoppers.
 */

it('leaves the blade tracer out of storefront pages while it is switched off', function () {
    expect(config('view.tracer'))->toBeFalse();

    get('/')
        ->assertOk()
        ->assertDontSee('blade-path-label', false)
        ->assertDontSee('[data-blade-path]', false);
});
