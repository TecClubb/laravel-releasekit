<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('releasekit.tables.links', 'app_release_links'), function (Blueprint $table) {
            $table->id();
            $table->string('platform', 50);
            $table->foreignId('app_release_id')
                ->nullable()
                ->constrained(config('releasekit.tables.releases', 'app_releases'))
                ->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('store', 50)->nullable();
            $table->string('label', 100)->nullable();
            $table->string('url', 2048);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['platform', 'app_release_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('releasekit.tables.links', 'app_release_links'));
    }
};
