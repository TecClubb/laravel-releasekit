<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('releasekit.tables.stats', 'app_release_stats'), function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('event', 20);
            $table->string('platform', 50);
            $table->string('channel', 50);
            // Dimensions use '' and 0 instead of NULL so the unique index can back atomic upserts.
            $table->string('version', 50)->default('');
            $table->string('architecture', 50)->default('');
            $table->unsignedBigInteger('artifact_id')->default(0); // no FK, so stats survive deletion
            $table->char('country', 2)->default('');
            $table->unsignedBigInteger('count')->default(0);

            $table->unique(
                ['date', 'event', 'platform', 'channel', 'version', 'architecture', 'artifact_id', 'country'],
                'releasekit_stats_dimensions_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('releasekit.tables.stats', 'app_release_stats'));
    }
};
