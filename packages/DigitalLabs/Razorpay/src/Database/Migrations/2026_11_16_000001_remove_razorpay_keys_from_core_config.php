<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The keys moved to the server's .env; drop the copies saved through the admin panel
     * so they no longer sit in the database or its backups.
     */
    public function up(): void
    {
        DB::table('core_config')->whereIn('code', [
            'sales.payment_methods.razorpay.key_id',
            'sales.payment_methods.razorpay.key_secret',
            'sales.payment_methods.razorpay.webhook_secret',
        ])->delete();
    }

    /**
     * Nothing to restore: the keys are read from .env.
     */
    public function down(): void {}
};
