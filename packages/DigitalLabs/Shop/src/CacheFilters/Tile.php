<?php

namespace DigitalLabs\Shop\CacheFilters;

use Intervention\Image\Filters\FilterInterface;
use Intervention\Image\Image;

/**
 * Photo tiles of about 150-340 css px, such as the home page's category tiles: the image scaled
 * down to fit 600 x 600 (enough for a phone tile at 3x), never cropped or enlarged, so it keeps the
 * shape of the original it stands in for in a srcset.
 */
class Tile implements FilterInterface
{
    public const SIZE = 600;

    public function applyFilter(Image $image)
    {
        return $image->resize(self::SIZE, self::SIZE, function ($constraint) {
            $constraint->aspectRatio();

            $constraint->upsize();
        });
    }
}
