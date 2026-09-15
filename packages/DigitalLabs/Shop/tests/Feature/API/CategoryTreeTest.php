<?php

use Webkul\Faker\Helpers\Category as CategoryFaker;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

it('returns a stamp alongside the category tree', function () {
    getJson(route('shop.api.categories.tree'))
        ->assertOk()
        ->assertJsonStructure(['data', 'stamp']);
});

it('changes the stamp when a category is created', function () {
    $before = getJson(route('shop.api.categories.tree'))->json('stamp');

    (new CategoryFaker)->factory()->create(['parent_id' => 1]);

    $after = getJson(route('shop.api.categories.tree'))->json('stamp');

    expect($after)->not->toBe($before);
});

it('changes the stamp when a category is deleted, even if it is not the most recently updated one', function () {
    $older = (new CategoryFaker)->factory()->create(['parent_id' => 1]);
    (new CategoryFaker)->factory()->create(['parent_id' => 1]);

    $before = getJson(route('shop.api.categories.tree'))->json('stamp');

    $older->delete();

    $after = getJson(route('shop.api.categories.tree'))->json('stamp');

    // A stamp built only from max(updated_at) would miss this: deleting a row
    // that isn't the most recently updated one leaves that max unchanged.
    expect($after)->not->toBe($before);
});

it('embeds the same stamp on the home page as the tree API returns', function () {
    $apiStamp = getJson(route('shop.api.categories.tree'))->json('stamp');

    $home = get(route('shop.home.index'))->assertOk()->content();

    expect($home)->toContain(json_encode($apiStamp));
});
