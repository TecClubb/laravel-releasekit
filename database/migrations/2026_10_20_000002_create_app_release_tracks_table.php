<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('releasekit.tables.tracks', 'app_release_tracks'), function (Blueprint $table) {
            $table->id();
            $table->string('platform', 50);
            $table->string('channel', 50);
            $table->foreignId('current_release_id')
                ->nullable()
                ->constrained(config('releasekit.tables.releases', 'app_releases'))
                ->restrictOnDelete();
            $table->string('minimum_supported_version', 50)->nullable();
            $table->timestamps();

            $table->unique(['platform', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('releasekit.tables.tracks', 'app_release_tracks'));
    }
};
