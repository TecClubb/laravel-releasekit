<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('releasekit.tables.artifacts', 'app_release_artifacts'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_release_id')
                ->constrained(config('releasekit.tables.releases', 'app_releases'))
                ->cascadeOnDelete();
            $table->string('architecture', 50)->nullable();
            $table->string('format', 20);
            $table->string('label', 100)->nullable();
            $table->string('disk', 64);
            $table->string('path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['app_release_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('releasekit.tables.artifacts', 'app_release_artifacts'));
    }
};
