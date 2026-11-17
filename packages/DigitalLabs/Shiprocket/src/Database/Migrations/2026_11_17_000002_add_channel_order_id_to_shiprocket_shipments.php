<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The order number sent to Shiprocket: the store's increment id, then `{increment id}-2`,
 * `-3`, … for each booking made again after a cancel (Shiprocket may refuse a reused number).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shiprocket_shipments', function (Blueprint $table) {
            $table->string('channel_order_id', 64)->nullable()->after('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('shiprocket_shipments', function (Blueprint $table) {
            $table->dropColumn('channel_order_id');
        });
    }
};
