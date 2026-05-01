<?php

namespace DigitalLabs\Theme\Console\Commands;

use Illuminate\Console\Command;
use DigitalLabs\Theme\Models\ThemeCustomization;
use DigitalLabs\Theme\Models\ThemeCustomizationTranslation;

class SyncHomeProductCarouselCategoryCommand extends Command
{
    /**
     * Mirrors saving the home “all products” carousel in Admin → Settings → Themes
     * (product_carousel options.filters.category_id).
     */
    protected $signature = 'theme:sync-home-carousel-category
        {--theme-id=9 : theme_customizations.id (installer default for bottom product carousel)}
        {--category-id=3 : Category id passed to shop.api.products.index as category_id}';

    protected $description = 'Set category filter on the home page product carousel (same DB update as Theme admin UI).';

    public function handle(): int
    {
        $themeId = (int) $this->option('theme-id');
        $categoryId = (string) $this->option('category-id');

        $theme = ThemeCustomization::query()->find($themeId);

        if (! $theme) {
            $this->error("No theme customization with id [{$themeId}]. Try: php artisan theme:sync-home-carousel-category --theme-id=?");

            return self::FAILURE;
        }

        if ($theme->type !== ThemeCustomization::PRODUCT_CAROUSEL) {
            $this->error("Theme customization [{$themeId}] is type [{$theme->type}], not product_carousel.");

            return self::FAILURE;
        }

        $rows = ThemeCustomizationTranslation::query()
            ->where('theme_customization_id', $themeId)
            ->get();

        if ($rows->isEmpty()) {
            $this->error('No translation rows found for this theme customization.');

            return self::FAILURE;
        }

        foreach ($rows as $translation) {
            $options = $translation->options ?? [];
            $filters = $options['filters'] ?? [];

            $filters['category_id'] = $categoryId;

            ksort($filters);

            $options['filters'] = $filters;

            $translation->options = $options;
            $translation->save();

            $this->line("Locale [{$translation->locale}]: filters.category_id => {$categoryId}");
        }

        $this->info("Updated product carousel #{$themeId} ({$theme->name}) for all locales.");

        return self::SUCCESS;
    }
}
