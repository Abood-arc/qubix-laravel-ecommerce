<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class PriceRuleReindex
{
    /**
     * After the daily catalog price-rule reindex, cached product/category pages may show
     * yesterday's price.
     *
     * @return void
     */
    public function afterReindex()
    {
        ResponseCache::clear();
    }
}
