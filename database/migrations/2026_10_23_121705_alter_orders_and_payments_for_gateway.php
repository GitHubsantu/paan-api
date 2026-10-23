<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_status', 20)->default('pending')->change();
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->boolean('refund_pending')->default(false)->after('paid_at');
        });

        DB::table('orders')->update(['payment_status' => 'paid']);

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('transaction_id')->nullable()->unique()->after('order_id');
            $table->string('merchant_reference')->nullable()->unique()->after('transaction_id');
            $table->text('checkout_url')->nullable()->after('status');
            $table->string('failure_reason')->nullable()->after('checkout_url');
            $table->json('raw_payload')->nullable()->after('failure_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['transaction_id']);
            $table->dropUnique(['merchant_reference']);
            $table->dropColumn(['transaction_id', 'merchant_reference', 'checkout_url', 'failure_reason', 'raw_payload']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['paid_at', 'refund_pending']);
            $table->string('payment_status', 20)->default('pending')->change();
        });
    }
};
