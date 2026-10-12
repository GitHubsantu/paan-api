<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_phone', 10)->nullable()->after('customer_name');
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('admin_activity_logs', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['customer_name', 'customer_phone']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
