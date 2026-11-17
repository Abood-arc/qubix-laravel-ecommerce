<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which booking of the order is current. Every cancel starts a new one, and a queued
 * booking job only runs for the generation it was sent for, so a retry left over from
 * before a cancel can never book the parcel again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shiprocket_shipments', function (Blueprint $table) {
            $table->unsignedInteger('generation')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('shiprocket_shipments', function (Blueprint $table) {
            $table->dropColumn('generation');
        });
    }
};
