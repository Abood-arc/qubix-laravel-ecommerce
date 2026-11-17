<?php

/*
 * Seed for tests/shiprocket.spec.ts. Run it against a THROWAWAY database (never a live or shared one):
 *
 *   php artisan tinker --execute="require 'packages/DigitalLabs/Shop/tests/e2e-pw/utils/shiprocket-seed.php';"
 *
 * Idempotent. Makes the store look like the Indian store (INR, grams), turns Shiprocket on with
 * its PIN check (no automatic booking), keeps Cash on Delivery, Money Transfer and Free Shipping
 * active, and creates one in-stock simple product (url key `shiprocket-e2e-bag`, 500 g).
 * Run it with the same CACHE_STORE as the app under test (see the fake AWB counter below).
 */

use DigitalLabs\Product\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Faker\Helpers\Product as ProductFaker;

$now = now();

// INR as the channel's only (and base) currency.
if (! DB::table('currencies')->where('code', 'INR')->exists()) {
    DB::table('currencies')->insert([
        'code' => 'INR',
        'name' => 'Indian Rupee',
        'symbol' => '₹',
        'decimal' => 2,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

$inr = DB::table('currencies')->where('code', 'INR')->value('id');

$channel = DB::table('channels')->where('code', 'default')->first();

DB::table('channels')->where('id', $channel->id)->update(['base_currency_id' => $inr]);

DB::table('channel_currencies')->where('channel_id', $channel->id)->delete();

DB::table('channel_currencies')->insert(['channel_id' => $channel->id, 'currency_id' => $inr]);

// Shiprocket settings (not channel or locale based).
$settings = [
    'sales.shipping.shiprocket.active' => '1',
    'sales.shipping.shiprocket.pin_check' => '1',
    'sales.shipping.shiprocket.auto_send' => '0',
    'sales.shipping.shiprocket.pickup_location' => 'Primary',
    'sales.shipping.shiprocket.pickup_pin' => '110001',
    'sales.shipping.shiprocket.length' => '40',
    'sales.shipping.shiprocket.breadth' => '30',
    'sales.shipping.shiprocket.height' => '3',
    'sales.shipping.shiprocket.max_weight' => '10',
];

foreach ($settings as $code => $value) {
    DB::table('core_config')->updateOrInsert(
        ['code' => $code, 'channel_code' => null, 'locale_code' => null],
        ['value' => $value, 'created_at' => $now, 'updated_at' => $now],
    );
}

// Channel-based settings: weight unit, payment and shipping methods.
$channelSettings = [
    'general.general.locale_options.weight_unit' => 'g',
    'sales.payment_methods.cashondelivery.active' => '1',
    'sales.payment_methods.moneytransfer.active' => '1',
    'sales.carriers.free.active' => '1',
];

foreach ($channelSettings as $code => $value) {
    DB::table('core_config')->where('code', $code)->whereNull('channel_code')->delete();

    DB::table('core_config')->updateOrInsert(
        ['code' => $code, 'channel_code' => $channel->code, 'locale_code' => null],
        ['value' => $value, 'created_at' => $now, 'updated_at' => $now],
    );
}

// One simple, visible, in-stock product weighing 500 g.
$sku = 'SHIPROCKET-E2E-BAG';

$product = Product::query()->where('sku', $sku)->first();

if (! $product) {
    $product = (new ProductFaker([
        'attribute_value' => [
            'sku' => ['text_value' => $sku],
            'name' => ['text_value' => 'Shiprocket E2E Bag', 'locale' => 'en'],
            'url_key' => ['text_value' => 'shiprocket-e2e-bag', 'locale' => 'en'],
            'price' => ['float_value' => 999],
            'weight' => ['text_value' => '500'],
        ],
    ]))->getSimpleProductFactory()->create(['sku' => $sku]);
}

DB::table('product_inventories')->where('product_id', $product->id)->update(['qty' => 1000]);

DB::table('product_ordered_inventories')->where('product_id', $product->id)->delete();

// The fake client numbers parcels (and so AWBs) from a cache counter. After a cache clear it would
// start again at 1 and reissue an AWB already in this database; keep it past the parcels on file.
$lastFakeShipment = (int) DB::table('shiprocket_shipments')->max('shipment_id');

if ($lastFakeShipment > 200000) {
    Cache::forever('shiprocket:fake:counter', max((int) Cache::get('shiprocket:fake:counter', 0), $lastFakeShipment - 200000));
}

echo json_encode([
    'product_id' => $product->id,
    'currency' => 'INR',
    'channel' => $channel->code,
]).PHP_EOL;
