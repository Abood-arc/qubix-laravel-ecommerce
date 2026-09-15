<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class Product
{
    /**
     * Update or create product page cache
     *
     * @param  \DigitalLabs\Product\Contracts\Product  $product
     * @return void
     */
    public function afterUpdate($product)
    {
        ResponseCache::clear();
    }

    /**
     * Delete product page cache
     *
     * @param  int  $productId
     * @return void
     */
    public function beforeDelete($productId)
    {
        ResponseCache::clear();
    }
}
