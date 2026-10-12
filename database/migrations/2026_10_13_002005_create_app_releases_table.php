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
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->default('android');
            $table->string('version_name', 30);
            $table->unsignedInteger('version_code');
            $table->text('release_notes')->nullable();
            $table->string('apk_path')->nullable();
            $table->string('apk_original_name')->nullable();
            $table->unsignedBigInteger('apk_size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('min_android', 10)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_current')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['platform', 'version_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
