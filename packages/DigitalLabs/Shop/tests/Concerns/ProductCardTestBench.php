<?php

namespace DigitalLabs\Shop\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Helpers for the product card tests (Feature/ProductCard).
 */
trait ProductCardTestBench
{
    /**
     * Store a setting for every channel, as an admin save would, or delete it when $value is
     * null. Then drop this process's cached copy, so the next read sees the database.
     */
    public function pinStoreConfig(string $code, ?string $value): void
    {
        DB::table('core_config')->where('code', $code)->delete();

        if ($value !== null) {
            foreach (DB::table('channels')->pluck('code') as $channel) {
                DB::table('core_config')->insert([
                    'code' => $code,
                    'value' => $value,
                    'channel_code' => $channel,
                    'locale_code' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        system_config()->flushCoreConfig();
    }

    /**
     * The settings the Classic card template depends on, pinned to the values the
     * fixture was recorded with, so the fixture does not depend on the database's settings.
     */
    public function pinClassicCardConfig(): void
    {
        $this->pinStoreConfig('customer.settings.wishlist.wishlist_option', '1');
        $this->pinStoreConfig('catalog.products.settings.compare_option', '1');
        $this->pinStoreConfig('sales.checkout.shopping_cart.cart_page', '1');
        $this->pinStoreConfig('catalog.products.review.summary', 'review_counts');
    }

    /**
     * A search results page always carries the product card templates (grid and list).
     */
    public function searchPageHtml(): string
    {
        return $this->get(route('shop.search.index', ['query' => 'bag', 'suggest' => '0']))
            ->assertOk()
            ->getContent();
    }

    /**
     * The v-product-card x-template from a page. Whitespace is collapsed the way Vue's
     * compiler condenses it, and the site URL is replaced so the result does not depend on APP_URL.
     */
    public function productCardTemplate(string $html): string
    {
        preg_match('#<script\s+type="text/x-template"\s+id="v-product-card-template"\s*>(.*?)</script>#s', $html, $match);

        expect($match)->not->toBeEmpty('The page has no v-product-card template');

        return trim(preg_replace('/\s+/', ' ', str_replace(rtrim(url('/'), '/'), '{site}', $match[1])));
    }

    public function classicCardFixturePath(): string
    {
        return dirname(__DIR__).'/Feature/ProductCard/fixtures/classic-card-template.html';
    }

    /**
     * Today's card template, recorded from `abood` before the card styles existed.
     */
    public function classicCardFixture(): string
    {
        expect(file_exists($this->classicCardFixturePath()))
            ->toBeTrue('No Classic card fixture yet: record it on unchanged abood code with UPDATE_CLASSIC_CARD_FIXTURE=1');

        return trim(file_get_contents($this->classicCardFixturePath()));
    }

    /**
     * The list-view half of a card template.
     */
    public function listCardMarkup(string $template): string
    {
        expect($template)->toContain('<!-- List Card -->');

        return Str::after($template, '<!-- List Card -->');
    }
}
