<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('releasekit.tables.releases', 'app_releases'), function (Blueprint $table) {
            $table->id();
            $table->string('platform', 50);
            $table->string('channel', 50)->default('stable');
            $table->string('version', 50);
            $table->string('build_number', 50)->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('release_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('yanked_at')->nullable();
            $table->string('yank_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['platform', 'channel', 'version']);
            $table->index(['platform', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('releasekit.tables.releases', 'app_releases'));
    }
};
