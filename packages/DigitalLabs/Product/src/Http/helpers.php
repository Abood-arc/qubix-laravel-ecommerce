<?php

use DigitalLabs\Product\Facades\ProductImage;
use DigitalLabs\Product\Facades\ProductVideo;
use DigitalLabs\Product\Helpers\Toolbar;

if (! function_exists('product_image')) {
    /**
     * Product image helper.
     *
     * @return \DigitalLabs\Product\ProductImage
     */
    function product_image()
    {
        return ProductImage::getFacadeRoot();
    }
}

if (! function_exists('product_video')) {
    /**
     * Product video helper.
     *
     * @return \DigitalLabs\Product\ProductVideo
     */
    function product_video()
    {
        return ProductVideo::getFacadeRoot();
    }
}

if (! function_exists('product_toolbar')) {
    /**
     * Product tolbar helper.
     *
     * @return \DigitalLabs\Product\Helpers\Toolbar
     */
    function product_toolbar()
    {
        return app()->make(Toolbar::class);
    }
}
