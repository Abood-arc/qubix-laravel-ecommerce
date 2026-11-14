<?php

use function Pest\Laravel\get;

it('returns not found for a url that cannot be a product or category slug', function (string $url) {
    get($url)->assertNotFound();
})->with([
    'php file probe' => '/wp-login.php',
    'dotfile probe' => '/.env',
    'nested file probe' => '/wp-admin/setup-config.php',
]);

it('returns not found for a valid slug that matches nothing', function () {
    get('/no-such-product-or-category')->assertNotFound();
});
