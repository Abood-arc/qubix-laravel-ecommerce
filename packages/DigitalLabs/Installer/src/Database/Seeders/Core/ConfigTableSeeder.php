<?php

namespace DigitalLabs\Installer\Database\Seeders\Core;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigTableSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('core_config')->delete();

        $now = Carbon::now();

        DB::table('core_config')->insert([
            'id' => 1,
            'code' => 'sales.checkout.shopping_cart.allow_guest_checkout',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 2,
            'code' => 'emails.general.notifications.emails.general.notifications.registration',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 3,
            'code' => 'emails.general.notifications.emails.general.notifications.customer_registration_confirmation_mail_to_admin',
            'value' => '0',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 4,
            'code' => 'emails.general.notifications.emails.general.notifications.customer_account_credentials',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 5,
            'code' => 'emails.general.notifications.emails.general.notifications.new_order',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 6,
            'code' => 'emails.general.notifications.emails.general.notifications.new_order_mail_to_admin',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 7,
            'code' => 'emails.general.notifications.emails.general.notifications.new_invoice',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 8,
            'code' => 'emails.general.notifications.emails.general.notifications.new_invoice_mail_to_admin',
            'value' => '0',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 9,
            'code' => 'emails.general.notifications.emails.general.notifications.new_refund',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 10,
            'code' => 'emails.general.notifications.emails.general.notifications.new_refund_mail_to_admin',
            'value' => '0',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 11,
            'code' => 'emails.general.notifications.emails.general.notifications.new_shipment',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 12,
            'code' => 'emails.general.notifications.emails.general.notifications.new_shipment_mail_to_admin',
            'value' => '0',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Second layer of defense alongside the seeded warehouse address
        // itself (InventorySourceTableSeeder): defaults off, same as its
        // sibling `new_shipment_mail_to_admin` flag two entries above, so a
        // fresh install doesn't email an unconfigured inventory-source
        // address (customer name/company/full shipping address included,
        // per Admin\Listeners\Shipment::afterCreated() and
        // InventorySourceNotification) on the very first shipment created,
        // before an operator has had a chance to visit Settings >
        // Inventory Sources. Was previously '1' with no apparent
        // deliberate reason (its sibling flag right above already defaults
        // off) — an operator who wants this notification can turn it back
        // on once a real address is configured.
        DB::table('core_config')->insert([
            'id' => 13,
            'code' => 'emails.general.notifications.emails.general.notifications.new_inventory_source',
            'value' => '0',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 14,
            'code' => 'emails.general.notifications.emails.general.notifications.cancel_order',
            'value' => '1',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('core_config')->insert([
            'id' => 15,
            'code' => 'emails.general.notifications.emails.general.notifications.cancel_order_mail_to_admin',
            'value' => '0',
            'channel_code' => null,
            'locale_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
