<?php

namespace DigitalLabs\FPC\Listeners;

use DigitalLabs\FPC\Support\CacheClearer;

class ThemeCustomization
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected CacheClearer $cacheClearer) {}

    /**
     * After theme customization create
     *
     * @param  \DigitalLabs\Shop\Contracts\ThemeCustomization  $themeCustomization
     * @return void
     */
    public function afterCreate($themeCustomization)
    {
        $this->cacheClearer->clearOnce();
    }

    /**
     * After theme customization update
     *
     * @param  \DigitalLabs\Shop\Contracts\ThemeCustomization  $themeCustomization
     * @return void
     */
    public function afterUpdate($themeCustomization)
    {
        $this->cacheClearer->clearOnce();
    }

    /**
     * Before theme customization delete
     *
     * @param  int  $themeCustomizationId
     * @return void
     */
    public function beforeDelete($themeCustomizationId)
    {
        $this->cacheClearer->clearOnce();
    }
}
