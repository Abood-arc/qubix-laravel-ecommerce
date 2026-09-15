<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class Review
{
    /**
     * After review is updated
     *
     * @param  \DigitalLabs\Product\Contracts\Review  $review
     * @return void
     */
    public function afterUpdate($review)
    {
        ResponseCache::clear();
    }

    /**
     * Before review is deleted
     *
     * @param  int  $reviewId
     * @return void
     */
    public function beforeDelete($reviewId)
    {
        ResponseCache::clear();
    }
}
