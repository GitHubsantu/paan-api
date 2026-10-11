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
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('tag', 30)->nullable();
            $table->unsignedTinyInteger('prep_minutes')->default(3);
            $table->json('ingredients')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('token')->nullable();
            $table->date('token_date')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_channel', 30)->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['token_date', 'token']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('options')->nullable();
            $table->string('product_image_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['options', 'product_image_snapshot']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropUnique(['token_date', 'token']);
            $table->dropColumn(['token', 'token_date', 'payment_method', 'payment_channel', 'idempotency_key']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tag', 'prep_minutes', 'ingredients']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
