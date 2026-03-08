<?php

namespace DigitalLabs\FPC\Listeners;

use Spatie\ResponseCache\Facades\ResponseCache;
use DigitalLabs\Theme\Repositories\ThemeCustomizationRepository;

class ThemeCustomization
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected ThemeCustomizationRepository $themeCustomizationRepository) {}

    /**
     * After theme customization create
     *
     * @param  \DigitalLabs\Shop\Contracts\ThemeCustomization  $themeCustomization
     * @return void
     */
    public function afterCreate($themeCustomization)
    {
        if (in_array($themeCustomization->type, ['footer_links', 'services_content'])) {
            ResponseCache::clear();
        } else {
            ResponseCache::selectCachedItems()
                ->forUrls(config('app.url').'/')
                ->forget();
        }
    }

    /**
     * After theme customization update
     *
     * @param  \DigitalLabs\Shop\Contracts\ThemeCustomization  $themeCustomization
     * @return void
     */
    public function afterUpdate($themeCustomization)
    {
        if (in_array($themeCustomization->type, ['footer_links', 'services_content'])) {
            ResponseCache::clear();
        } else {
            ResponseCache::selectCachedItems()
                ->forUrls(config('app.url').'/')
                ->forget();
        }
    }

    /**
     * Before theme customization delete
     *
     * @param  int  $themeCustomizationId
     * @return void
     */
    public function beforeDelete($themeCustomizationId)
    {
        $themeCustomization = $this->themeCustomizationRepository->find($themeCustomizationId);

        if (in_array($themeCustomization->type, ['footer_links', 'services_content'])) {
            ResponseCache::clear();
        } else {
            ResponseCache::selectCachedItems()
                ->forUrls(config('app.url').'/')
                ->forget();
        }
    }
}
