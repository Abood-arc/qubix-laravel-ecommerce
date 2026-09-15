<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class Page
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After page update
     *
     * @param  \DigitalLabs\CMS\Contracts\Page  $page
     * @return void
     */
    public function afterUpdate($page)
    {
        $this->cacheClearer->clearOnce();
    }

    /**
     * Before page delete
     *
     * @param  int  $pageId
     * @return void
     */
    public function beforeDelete($pageId)
    {
        $this->cacheClearer->clearOnce();
    }
}
