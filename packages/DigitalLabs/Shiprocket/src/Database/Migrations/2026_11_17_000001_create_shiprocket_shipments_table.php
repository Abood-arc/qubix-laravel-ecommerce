<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiprocket_shipments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->unique();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('shiprocket_order_id')->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('awb_code', 64)->nullable()->unique();
            $table->string('courier_name')->nullable();
            $table->string('label_url', 1024)->nullable();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->string('last_event')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('store_shipment_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('store_shipment_id')->references('id')->on('shipments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shiprocket_shipments');
    }
};
