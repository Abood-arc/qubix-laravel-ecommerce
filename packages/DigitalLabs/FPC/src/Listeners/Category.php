<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class Category
{
    /**
     * After category create. Also covers the home page, which embeds the category tree.
     *
     * @param  \DigitalLabs\Category\Contracts\Category  $category
     * @return void
     */
    public function afterCreate($category)
    {
        ResponseCache::clear();
    }

    /**
     * After category update. Also covers the home page, which embeds the category tree.
     *
     * @param  \DigitalLabs\Category\Contracts\Category  $category
     * @return void
     */
    public function afterUpdate($category)
    {
        ResponseCache::clear();
    }

    /**
     * Before category delete. Also covers the home page, which embeds the category tree.
     *
     * @param  int  $categoryId
     * @return void
     */
    public function beforeDelete($categoryId)
    {
        ResponseCache::clear();
    }
}
