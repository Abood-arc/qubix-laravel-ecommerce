<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class Review
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After review is updated
     *
     * @param  \DigitalLabs\Product\Contracts\Review  $review
     * @return void
     */
    public function afterUpdate($review)
    {
        $this->cacheClearer->clearOnce();
    }

    /**
     * Before review is deleted
     *
     * @param  int  $reviewId
     * @return void
     */
    public function beforeDelete($reviewId)
    {
        $this->cacheClearer->clearOnce();
    }
}
