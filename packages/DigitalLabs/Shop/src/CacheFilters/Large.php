<?php

namespace DigitalLabs\Shop\CacheFilters;

use Illuminate\Support\Str;
use Intervention\Image\Filters\FilterInterface;
use Intervention\Image\Image;

class Large implements FilterInterface
{
    /**
     * Apply filter.
     *
     * @return \Intervention\Image\Image
     */
    public function applyFilter(Image $image)
    {
        /**
         * If the current url is product image
         */
        if (Str::contains(url()->current(), '/product')) {
            $width = core()->getConfigData('catalog.products.cache_large_image.width') != ''
                ? core()->getConfigData('catalog.products.cache_large_image.width')
                : 560;

            $height = core()->getConfigData('catalog.products.cache_large_image.height') != ''
                ? core()->getConfigData('catalog.products.cache_large_image.height')
                : 610;

            return $image->fit($width, $height);
        } elseif (Str::contains(url()->current(), '/category')) {
            return $image->fit(165, 165);
        } elseif (Str::contains(url()->current(), '/attribute_option')) {
            return $image->fit(330, 330);
        }

        /**
         * Slider image dimensions
         */
        if (Str::contains(url()->current(), '/theme/')) {
            /*
             * Theme images (hero banners) are listed in a srcset beside the uncropped original,
             * so a resized copy must keep the original's shape; cropping it here made the banner
             * show a different crop depending on screen size.
             */
            return $image->widen(1280, fn ($constraint) => $constraint->upsize());
        }

        return $image->fit(1280, 467);
    }
}
