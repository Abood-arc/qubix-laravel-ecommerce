<?php

namespace DigitalLabs\Installer\Database\Seeders\Inventory;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySourceTableSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('inventory_sources')->delete();

        $defaultLocale = $parameters['default_locale'] ?? config('app.locale');

        // Every field below is deliberately a "replace me" placeholder rather
        // than a specific fake business/city: the address fields avoid
        // implying a real US location for what will be a multi-country
        // fleet (jj-bags.com, for instance, is Saudi Arabia). `country` is
        // the one exception — it stays a real ISO code ('US') because
        // Admin's country/state dropdowns look it up against
        // core()->countries() and an invalid code would break that lookup.
        //
        // `contact_email` MUST resolve to nothing, not just "look fake" —
        // this address is a live mail recipient by default, not inert seed
        // data: Admin\Listeners\Shipment::afterCreated() emails this exact
        // address (name, company, full shipping address included) on every
        // shipment created while ConfigTableSeeder's `new_inventory_source`
        // notification flag is on, before an operator ever visits the
        // inventory-source settings page (that flag now defaults off,
        // ConfigTableSeeder id 13 — a second, independent layer of defense,
        // not a substitute for this address being safe on its own).
        // An earlier revision of this line used an ordinary,
        // real, third-party-registrable domain here, reasoning it read
        // better as "configure me" than the domain it replaced — true, but
        // that domain also carried no guarantee against ever accepting
        // mail, which was strictly worse than what it replaced (which was
        // IANA/RFC-2606-reserved and guaranteed undeliverable). `.invalid`
        // is also RFC 2606-reserved and can never resolve, so
        // `change-me.invalid` keeps the "configure me" framing without the
        // safety regression. Verified against both PHP's own `filter_var`
        // and this app's actual `InventorySourceRequest` validation rule
        // (`['required', 'email']`) — both accept it, so re-saving this row
        // unedited from the admin form doesn't fail validation.
        DB::table('inventory_sources')->insert([
            'id' => 1,
            'code' => 'default',
            'name' => trans('installer::app.seeders.inventory.inventory-sources.name', [], $defaultLocale),
            'contact_name' => trans('installer::app.seeders.inventory.inventory-sources.name', [], $defaultLocale),
            'contact_email' => 'warehouse@change-me.invalid',
            'contact_number' => '0000000000',
            'status' => 1,
            'country' => 'US',
            'state' => 'Update State',
            'street' => 'Update this address',
            'city' => 'Update City',
            'postcode' => '00000',
        ]);
    }
}
