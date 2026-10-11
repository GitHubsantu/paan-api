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
            $table->index('user_id');
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'idempotency_key', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'idempotency_key', 'created_at']);
            $table->unique(['user_id', 'idempotency_key']);
            $table->dropIndex(['user_id']);
        });
    }
};
