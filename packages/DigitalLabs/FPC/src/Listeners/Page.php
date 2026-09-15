<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class Page
{
    /**
     * After page update
     *
     * @param  \DigitalLabs\CMS\Contracts\Page  $page
     * @return void
     */
    public function afterUpdate($page)
    {
        ResponseCache::clear();
    }

    /**
     * Before page delete
     *
     * @param  int  $pageId
     * @return void
     */
    public function beforeDelete($pageId)
    {
        ResponseCache::clear();
    }
}
