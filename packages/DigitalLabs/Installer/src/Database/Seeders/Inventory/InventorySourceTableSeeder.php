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
        // than a specific fake business/city: `contact_email` uses a
        // placeholder-domain convention (not example.com — that reads as a
        // real demo/test artifact rather than "please configure this"), and
        // the address fields avoid implying a real US location for what will
        // be a multi-country fleet (jj-bags.com, for instance, is Saudi
        // Arabia). `country` is the one exception — it stays a real ISO code
        // ('US') because Admin's country/state dropdowns look it up against
        // core()->countries() and an invalid code would break that lookup.
        DB::table('inventory_sources')->insert([
            'id' => 1,
            'code' => 'default',
            'name' => trans('installer::app.seeders.inventory.inventory-sources.name', [], $defaultLocale),
            'contact_name' => trans('installer::app.seeders.inventory.inventory-sources.name', [], $defaultLocale),
            'contact_email' => 'warehouse@yourdomain.com',
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
