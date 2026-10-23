<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('payment_status', 'failed')
            ->where('status', 'pending')
            ->update(['status' => 'payment_failed', 'completed_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')
            ->where('status', 'payment_failed')
            ->update(['status' => 'pending', 'completed_at' => null, 'updated_at' => now()]);
    }
};
