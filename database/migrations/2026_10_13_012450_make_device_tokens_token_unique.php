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
        $idsToKeep = DB::table('device_tokens')->orderByDesc('id')->get()->unique('token')->pluck('id');
        DB::table('device_tokens')->whereNotIn('id', $idsToKeep)->delete();

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique('device_tokens_user_id_token_unique');
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->unique('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique('device_tokens_token_unique');
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->unique(['user_id', 'token']);
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropIndex('device_tokens_user_id_index');
        });
    }
};
