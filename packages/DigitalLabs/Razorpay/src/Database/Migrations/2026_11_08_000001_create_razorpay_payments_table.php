<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('razorpay_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cart_id')->index();
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->string('razorpay_refund_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('status')->default('created');
            $table->string('refund_reason')->nullable();
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_payments');
    }
};
