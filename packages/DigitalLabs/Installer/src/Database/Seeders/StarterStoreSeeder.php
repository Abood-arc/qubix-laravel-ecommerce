<?php

namespace DigitalLabs\Installer\Database\Seeders;

use DigitalLabs\Installer\Database\Seeders\Attribute\DatabaseSeeder as AttributeSeeder;
use DigitalLabs\Installer\Database\Seeders\Category\DatabaseSeeder as CategorySeeder;
use DigitalLabs\Installer\Database\Seeders\CMS\DatabaseSeeder as CMSSeeder;
use DigitalLabs\Installer\Database\Seeders\Core\DatabaseSeeder as CoreSeeder;
use DigitalLabs\Installer\Database\Seeders\Customer\DatabaseSeeder as CustomerSeeder;
use DigitalLabs\Installer\Database\Seeders\Inventory\DatabaseSeeder as InventorySeeder;
use DigitalLabs\Installer\Database\Seeders\Shop\ThemeCustomizationTableSeeder as ShopSeeder;
use DigitalLabs\Installer\Database\Seeders\SocialLogin\DatabaseSeeder as SocialLoginSeeder;
use DigitalLabs\Installer\Database\Seeders\User\DatabaseSeeder as UserSeeder;
use Illuminate\Database\Seeder;

/**
 * Seeds a brand-new client's database with a neutral, working starter store:
 * category/attribute structure, theme sections, CMS pages, a default
 * inventory source, and the roles/admin/channel/locale/currency scaffolding
 * needed for the store to actually be usable (browsable storefront,
 * loggable-into admin) on its own.
 *
 * This is a versioned seeder committed to git, replacing the older
 * clone-a-real-database-and-scrub-it approach (see
 * scripts/convert-to-saudi.sql) — that approach is a permanently growing
 * liability, since every future migration that adds a client-data table has
 * to be remembered and added to its TRUNCATE list or one client's data leaks
 * into the next client's store. A seeder has no leak surface (it only ever
 * inserts neutral rows) and is testable in CI.
 *
 * Deliberately calls the same 9 sub-seeders, in the same order, as
 * `DigitalLabs\Installer\Database\Seeders\DatabaseSeeder` (the chain
 * `qubix:install` itself uses) rather than a hand-picked subset — a
 * functional store needs the channel/locale/currency/customer-group/role
 * scaffolding those seeders provide, not just the "category and attribute
 * structure, theme sections, CMS pages, placeholder branding, a default
 * inventory source" the plan's own footprint text names. "Placeholder
 * branding" needs no seeded row at all: the storefront-branding config is
 * left unset, which is exactly what
 * `DigitalLabs\Core\Helpers\BrandPalette::derive()` already treats as its
 * neutral default.
 *
 * This is a separate class rather than a direct call to the Installer's own
 * `DatabaseSeeder`, even though the two are identical today, so that the
 * fleet-provisioning seed footprint can diverge independently from the
 * interactive installer's later — `qubix:install` serves a from-scratch
 * local dev setup, `StarterStoreSeeder` serves a paying client's first
 * launch, and those are different concerns that only happen to be identical
 * right now.
 */
class StarterStoreSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        $parameters = array_merge([
            'default_locale' => 'en',
            'allowed_locales' => ['en'],
            'default_currency' => 'USD',
            'allowed_currencies' => ['USD'],
        ], $parameters);

        $this->call(AttributeSeeder::class, false, ['parameters' => $parameters]);
        $this->call(CategorySeeder::class, false, ['parameters' => $parameters]);
        $this->call(CoreSeeder::class, false, ['parameters' => $parameters]);
        $this->call(CustomerSeeder::class, false, ['parameters' => $parameters]);
        $this->call(CMSSeeder::class, false, ['parameters' => $parameters]);
        $this->call(InventorySeeder::class, false, ['parameters' => $parameters]);
        $this->call(SocialLoginSeeder::class, false, ['parameters' => $parameters]);
        $this->call(ShopSeeder::class, false, ['parameters' => $parameters]);
        $this->call(UserSeeder::class, false, ['parameters' => $parameters]);
    }
}
