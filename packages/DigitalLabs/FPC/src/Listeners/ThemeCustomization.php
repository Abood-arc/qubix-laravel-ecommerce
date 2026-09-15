<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;

class ThemeCustomization
{
    /**
     * After theme customization create
     *
     * @param  \DigitalLabs\Shop\Contracts\ThemeCustomization  $themeCustomization
     * @return void
     */
    public function afterCreate($themeCustomization)
    {
        ResponseCache::clear();
    }

    /**
     * After theme customization update
     *
     * @param  \DigitalLabs\Shop\Contracts\ThemeCustomization  $themeCustomization
     * @return void
     */
    public function afterUpdate($themeCustomization)
    {
        ResponseCache::clear();
    }

    /**
     * Before theme customization delete
     *
     * @param  int  $themeCustomizationId
     * @return void
     */
    public function beforeDelete($themeCustomizationId)
    {
        ResponseCache::clear();
    }
}
