<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class Product
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * Update or create product page cache
     *
     * @param  \DigitalLabs\Product\Contracts\Product  $product
     * @return void
     */
    public function afterUpdate($product)
    {
        $this->cacheClearer->clearOnce();
    }

    /**
     * Delete product page cache
     *
     * @param  int  $productId
     * @return void
     */
    public function beforeDelete($productId)
    {
        $this->cacheClearer->clearOnce();
    }
}
