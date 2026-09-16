<?php

/**
 * Regression coverage for Task 2.1 (fleet-readiness): with no Elasticsearch
 * container in the new-client stack, `catalog.products.search.{engine,admin_mode,
 * storefront_mode}` must never be persisted as `elastic` again — every product
 * save would queue a job that throws (UpdateCreateIndex::handle()).
 *
 * These hit the real admin.configuration.store endpoint over HTTP (not tinker),
 * reconstructing the `keys[]` payload the same way
 * `configuration/field-type.blade.php` does, so the test exercises the same
 * ConfigurationForm::rules() code path a real admin submission would.
 */

use DigitalLabs\Core\SystemConfig;

use function Pest\Laravel\postJson;

/**
 * Build the same `keys[]` JSON blob the configuration-edit Blade view embeds
 * as a hidden input for the given third-level config group.
 */
function searchConfigItemJson(): string
{
    $searchItem = app(SystemConfig::class)
        ->getItems()
        ->firstWhere('key', 'catalog')
        ->getChildren()
        ->firstWhere('key', 'catalog.products')
        ->getChildren()
        ->firstWhere('key', 'catalog.products.search');

    expect($searchItem)->not->toBeNull();

    return json_encode($searchItem);
}

it('rejects elastic as the search engine value through the config-save endpoint', function () {
    $this->loginAsAdmin();

    postJson(route('admin.configuration.store'), [
        'keys' => [searchConfigItemJson()],
        'catalog' => [
            'products' => [
                'search' => [
                    'engine' => 'elastic',
                ],
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('catalog.products.search.engine');

    $this->assertDatabaseMissing('core_config', [
        'code' => 'catalog.products.search.engine',
        'value' => 'elastic',
    ]);
});

it('rejects elastic as the admin_mode and storefront_mode values through the config-save endpoint', function () {
    $this->loginAsAdmin();

    postJson(route('admin.configuration.store'), [
        'keys' => [searchConfigItemJson()],
        'catalog' => [
            'products' => [
                'search' => [
                    'admin_mode' => 'elastic',
                    'storefront_mode' => 'elastic',
                ],
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('catalog.products.search.admin_mode')
        ->assertJsonValidationErrorFor('catalog.products.search.storefront_mode');
});

it('still accepts database as the search engine value through the config-save endpoint', function () {
    $this->loginAsAdmin();

    $channel = core()->getDefaultChannel();

    postJson(route('admin.configuration.store'), [
        'keys' => [searchConfigItemJson()],
        // The real form sends these as hidden inputs (see configuration/edit.blade.php);
        // ConfigurationController::store() -> CoreConfigRepository::create() reads them
        // unconditionally from the top-level request array.
        'channel' => $channel->code,
        'locale' => $channel->default_locale?->code ?? $channel->locales->first()?->code,
        'catalog' => [
            'products' => [
                'search' => [
                    'engine' => 'database',
                ],
            ],
        ],
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('core_config', [
        'code' => 'catalog.products.search.engine',
        'value' => 'database',
    ]);
});
