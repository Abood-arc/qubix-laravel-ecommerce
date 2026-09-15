<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class Category
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After category create. Also covers the home page, which embeds the category tree.
     *
     * @param  \DigitalLabs\Category\Contracts\Category  $category
     * @return void
     */
    public function afterCreate($category)
    {
        $this->cacheClearer->clearOnce();
    }

    /**
     * After category update. Also covers the home page, which embeds the category tree.
     *
     * @param  \DigitalLabs\Category\Contracts\Category  $category
     * @return void
     */
    public function afterUpdate($category)
    {
        $this->cacheClearer->clearOnce();
    }

    /**
     * Before category delete. Also covers the home page, which embeds the category tree.
     *
     * @param  int  $categoryId
     * @return void
     */
    public function beforeDelete($categoryId)
    {
        $this->cacheClearer->clearOnce();
    }
}
