<?php

use DigitalLabs\Shop\CacheFilters\Tile;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\get;

/*
 * The `tile` image cache template (/cache/tile/..., CacheFilters\Tile), used for the home page's
 * category tiles: the photo scaled down to fit 600 x 600, never cropped and never enlarged.
 */

$folder = fn () => storage_path('app/public/tile-image-cache-test');

beforeEach(function () use ($folder) {
    File::ensureDirectoryExists($folder());
});

afterEach(function () use ($folder) {
    File::deleteDirectory($folder());
});

$photo = function (string $name, int $width, int $height) use ($folder): string {
    $image = imagecreatetruecolor($width, $height);

    imagepng($image, $folder().'/'.$name);

    return 'tile-image-cache-test/'.$name;
};

it('scales a large photo down to fit 600 x 600, keeping its shape', function () use ($photo) {
    $response = get('cache/tile/'.$photo('wide.png', 1200, 900))->assertOk();

    expect(array_slice(getimagesizefromstring($response->getContent()), 0, 2))->toBe([Tile::SIZE, 450]);
});

it('leaves a photo that already fits as it is', function () use ($photo) {
    $response = get('cache/tile/'.$photo('small.png', 300, 200))->assertOk();

    expect(array_slice(getimagesizefromstring($response->getContent()), 0, 2))->toBe([300, 200]);
});
