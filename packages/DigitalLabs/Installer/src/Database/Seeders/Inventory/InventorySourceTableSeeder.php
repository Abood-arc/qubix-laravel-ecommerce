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

        DB::table('inventory_sources')->insert([
            'id' => 1,
            'code' => 'default',
            'name' => trans('installer::app.seeders.inventory.inventory-sources.name', [], $defaultLocale),
            'contact_name' => trans('installer::app.seeders.inventory.inventory-sources.name', [], $defaultLocale),
            // Placeholder-domain convention (not example.com — that reads as a
            // real demo/test artifact rather than "please configure this"),
            // and a US-specific fake address would be misleading for a
            // multi-country fleet. `country` stays a real ISO code ('US') so
            // Admin's country/state dropdowns (which look it up against
            // core()->countries()) keep rendering correctly; the rest just
            // needs to obviously read as "replace me", not imply a real place.
            'contact_email' => 'warehouse@yourdomain.com',
            'contact_number' => 1234567899,
            'status' => 1,
            'country' => 'US',
            'state' => 'Update State',
            'street' => 'Update this address',
            'city' => 'Update City',
            'postcode' => '00000',
        ]);
    }
}
