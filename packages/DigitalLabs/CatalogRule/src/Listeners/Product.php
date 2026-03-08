<?php

namespace DigitalLabs\CatalogRule\Listeners;

use DigitalLabs\CatalogRule\Jobs\UpdateCreateProductIndex as UpdateCreateProductIndexJob;

class Product
{
    /**
     * @param  \DigitalLabs\Product\Contracts\Product  $product
     * @return void
     */
    public function afterUpdate($product)
    {
        UpdateCreateProductIndexJob::dispatch($product);
    }
}
