<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The courier the customer paid for, kept only when Shiprocket refused it and booking fell
 * back to Shiprocket's own choice, so the admin can see the parcel went with another courier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shiprocket_shipments', function (Blueprint $table) {
            $table->unsignedInteger('requested_courier_id')->nullable()->after('courier_name');
        });
    }

    public function down(): void
    {
        Schema::table('shiprocket_shipments', function (Blueprint $table) {
            $table->dropColumn('requested_courier_id');
        });
    }
};
