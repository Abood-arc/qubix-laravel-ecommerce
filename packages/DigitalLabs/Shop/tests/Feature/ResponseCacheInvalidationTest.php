<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Spatie\ResponseCache\Facades\ResponseCache;
use DigitalLabs\Core\Core as CoreService;
use DigitalLabs\Attribute\Models\Attribute;
use DigitalLabs\CMS\Models\Page;
use DigitalLabs\Theme\Models\ThemeCustomization;
use DigitalLabs\User\Models\Admin;
use Webkul\Faker\Helpers\Category as CategoryFaker;
use Webkul\Faker\Helpers\Product as ProductFaker;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * These tests target the "default" channel (hostname jjbags.in), matching the live
 * jjbags.in stack and CLAUDE.md's note that localhost:8080 resolves to the local-only
 * "Channel2" instead. Full-page caching is on locally (RESPONSE_CACHE_ENABLED=true),
 * runs under Pest (BaseCacheProfile::isRunningInConsole() is false in testing), and
 * uses the file cache store — so cache entries can be counted on disk exactly as
 * documented in CLAUDE.md, rather than by asserting on the (APP_DEBUG-gated) cache
 * header or by dispatching events directly, which would skip the real HTTP/hasher path.
 */
function fpcCacheFileCount(): int
{
    $path = storage_path('framework/cache/data');

    return File::exists($path) ? count(File::allFiles($path)) : 0;
}

function fpcSetLocale(string $locale): void
{
    // The DigitalLabs\Core\Facades\Core facade memoizes its resolved locale/channel
    // for as long as the facade cache holds the instance, which — unlike production,
    // where every request is a fresh PHP process — spans every get()/post() call
    // within a single Pest test. Without clearing it, a locale switch mid-test would
    // never be seen by core()->getCurrentLocale(), and every "second locale" request
    // would silently reuse the first one's cache key.
    Facade::clearResolvedInstance(CoreService::class);

    app()->setLocale($locale);
    session()->put('locale', $locale);
}

function fpcLoginAsAdmin(): Admin
{
    $admin = Admin::factory()->create();

    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    config(['app.url' => 'https://jjbags.in']);
    URL::forceRootUrl('https://jjbags.in');

    ResponseCache::clear();
});

it('clears the whole cache when a category is created, since the home page embeds the category tree', function () {
    // Arrange.
    $attributes = Attribute::where('is_filterable', 1)->pluck('id')->toArray();

    get('/')->assertOk();

    expect(fpcCacheFileCount())->toBe(1);

    // Act.
    fpcLoginAsAdmin();

    postJson(route('admin.catalog.categories.store'), [
        'slug' => fake()->slug(),
        'name' => fake()->name(),
        'position' => 1,
        'parent_id' => 1,
        'status' => 1,
        'attributes' => $attributes,
        'logo_path' => [UploadedFile::fake()->image('logo.png')],
        'banner_path' => [UploadedFile::fake()->image('banner.png')],
    ])->assertRedirect();

    // Assert.
    expect(fpcCacheFileCount())->toBe(0);
});

it('clears the whole cache when a category is updated, including a second locale of the home page', function () {
    // Arrange.
    $category = (new CategoryFaker)->factory()->create(['parent_id' => 1]);

    $attributes = Attribute::where('is_filterable', 1)->pluck('id')->toArray();

    ResponseCache::clear();

    fpcSetLocale('en');
    get('/')->assertOk();

    fpcSetLocale('ar');
    get('/')->assertOk();

    fpcSetLocale('en');
    get('/'.$category->slug)->assertOk();

    expect(fpcCacheFileCount())->toBe(3);

    // Act.
    fpcLoginAsAdmin();

    putJson(route('admin.catalog.categories.update', $category->id), [
        'en' => [
            'name' => fake()->name(),
            'description' => substr(fake()->paragraph(), 0, 50),
            'slug' => $category->slug,
        ],
        'locale' => 'en',
        'attributes' => $attributes,
        'position' => 1,
        'logo_path' => [UploadedFile::fake()->image('logo.png')],
        'banner_path' => [UploadedFile::fake()->image('banner.png')],
    ])->assertRedirect();

    // Assert.
    expect(fpcCacheFileCount())->toBe(0);
});

it('clears the whole cache when theme customization is updated, not just the home page', function () {
    // Arrange.
    $theme = ThemeCustomization::factory()->create([
        'type' => 'product_carousel',
    ]);

    $category = (new CategoryFaker)->factory()->create(['parent_id' => 1]);

    $product = (new ProductFaker([
        'attributes' => [5 => 'new', 6 => 'featured', 11 => 'price', 26 => 'guest_checkout'],
        'attribute_value' => [
            'new' => ['boolean_value' => true],
            'featured' => ['boolean_value' => true],
            'price' => ['float_value' => 1999],
            'guest_checkout' => ['boolean_value' => true],
        ],
    ]))->getSimpleProductFactory()->create();

    get('/')->assertOk();
    get('/'.$category->slug)->assertOk();
    get('/'.$product->url_key)->assertOk();

    expect(fpcCacheFileCount())->toBe(3);

    // Act.
    fpcLoginAsAdmin();

    postJson(route('admin.settings.themes.update', $theme->id), [
        app()->getLocale() => [
            'options' => [
                'title' => fake()->title(),
                'filters' => ['sort' => 'name-desc', 'limit' => '12', 'new' => '1'],
            ],
        ],
        'locale' => app()->getLocale(),
        'type' => 'product_carousel',
        'name' => fake()->name(),
        'sort_order' => '1',
        'channel_id' => core()->getCurrentChannel()->id,
        'theme_code' => core()->getCurrentChannel()->theme,
        'status' => 'on',
    ])->assertRedirect();

    // Assert. Prior to the fix, this listener only forgot the home URL for the
    // admin's own channel/locale/currency/guest variant, leaving the category and
    // product pages (and any other locale/currency/customer variant) stale.
    expect(fpcCacheFileCount())->toBe(0);
});

it('clears the whole cache when a product is updated, including a logged-in customer\'s cached copy', function () {
    // Arrange.
    $product = (new ProductFaker)->getSimpleProductFactory()->create();

    get('/'.$product->url_key)->assertOk();
    test()->loginAsCustomer();
    get('/'.$product->url_key)->assertOk();

    expect(fpcCacheFileCount())->toBe(2);

    // Act.
    fpcLoginAsAdmin();

    putJson(route('admin.catalog.products.update', $product->id), [
        'sku' => $product->sku,
        'url_key' => $product->url_key,
        'short_description' => fake()->sentence(),
        'description' => fake()->paragraph(),
        'name' => fake()->words(3, true),
        'price' => fake()->randomFloat(2, 1, 1000),
        'weight' => fake()->numberBetween(0, 100),
        'channel' => core()->getCurrentChannelCode(),
        'locale' => app()->getLocale(),
    ])->assertRedirect();

    // Assert. Prior to the fix, the selective forget only matched the guest-suffixed
    // cache key, leaving the logged-in customer's cached copy stale for up to 7 days.
    expect(fpcCacheFileCount())->toBe(0);
});

it('clears the whole cache when a CMS page is updated, including a logged-in customer\'s cached copy', function () {
    // Arrange.
    $page = Page::factory()->hasTranslations()->create();
    $page->channels()->sync([1]);

    get('/page/'.$page->url_key)->assertOk();
    test()->loginAsCustomer();
    get('/page/'.$page->url_key)->assertOk();

    expect(fpcCacheFileCount())->toBe(2);

    // Act.
    fpcLoginAsAdmin();

    putJson(route('admin.cms.update', $page->id), [
        core()->getCurrentLocale()->code => [
            'url_key' => $page->url_key,
            'page_title' => fake()->word(),
            'html_content' => substr(fake()->paragraph(), 0, 50),
        ],
        'locale' => core()->getCurrentLocale()->code,
        'channels' => [1],
    ])->assertRedirect();

    // Assert.
    expect(fpcCacheFileCount())->toBe(0);
});

it('clears the whole cache after the daily catalog price-rule reindex', function () {
    // Arrange.
    get('/')->assertOk();

    expect(fpcCacheFileCount())->toBe(1);

    // Act. The scheduled command dispatches no event today, so cached product/category
    // pages keep yesterday's price for up to the 7-day cache lifetime.
    Artisan::call('product:price-rule:index');

    // Assert.
    expect(fpcCacheFileCount())->toBe(0);
});

it('does not clear the cache for an unrelated write', function () {
    // Arrange.
    get('/')->assertOk();

    expect(fpcCacheFileCount())->toBe(1);

    // Act. Creating a customer is not wired to any FPC listener.
    (new Webkul\Faker\Helpers\Customer)->factory()->create();

    // Assert. A flush-on-everything "fix" would silently disable caching entirely;
    // this proves an unrelated write is left alone.
    expect(fpcCacheFileCount())->toBe(1);
});
