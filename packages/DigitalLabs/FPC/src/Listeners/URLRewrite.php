<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class URLRewrite
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After URL Rewrite update
     *
     * @param  \DigitalLabs\Marketing\Contracts\URLRewrite  $urlRewrite
     * @return void
     */
    public function afterUpdate($urlRewrite)
    {
        $this->cacheClearer->clearOnce();
    }

    /**
     * Before URL Rewrite delete
     *
     * @param  int  $urlRewriteId
     * @return void
     */
    public function beforeDelete($urlRewriteId)
    {
        $this->cacheClearer->clearOnce();
    }
}
