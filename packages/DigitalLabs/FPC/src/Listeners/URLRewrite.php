<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class URLRewrite
{
    /**
     * After URL Rewrite update
     *
     * @param  \DigitalLabs\Marketing\Contracts\URLRewrite  $urlRewrite
     * @return void
     */
    public function afterUpdate($urlRewrite)
    {
        ResponseCache::clear();
    }

    /**
     * Before URL Rewrite delete
     *
     * @param  int  $urlRewriteId
     * @return void
     */
    public function beforeDelete($urlRewriteId)
    {
        ResponseCache::clear();
    }
}
