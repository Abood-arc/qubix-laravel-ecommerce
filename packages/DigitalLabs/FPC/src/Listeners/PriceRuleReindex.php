<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class PriceRuleReindex
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After the daily catalog price-rule reindex, cached product/category pages may show
     * yesterday's price.
     *
     * @return void
     */
    public function afterReindex()
    {
        $this->cacheClearer->clearOnce();
    }
}
