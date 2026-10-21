<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = config('releasekit.tables.stats', 'app_release_stats');

        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->id();
            $table->date('date');
            $table->string('event', 20);
            $table->string('platform', 50);
            $table->string('channel', 50);
            // Dimensions use '' and 0 instead of NULL so the unique index can back atomic upserts.
            $table->string('version', 50)->default('');
            $table->string('architecture', 50)->default('');
            $table->unsignedBigInteger('artifact_id')->default(0); // no FK, so stats survive deletion
            // varchar, not char: PostgreSQL pads char(2) so '' would read back as '  '.
            $table->string('country', 2)->default('');
            $table->unsignedBigInteger('count')->default(0);

            $table->unique(
                ['date', 'event', 'platform', 'channel', 'version', 'architecture', 'artifact_id', 'country'],
                // Index names are schema-wide on SQLite/PostgreSQL, so derive it from the table (max 64 chars for MySQL).
                substr("{$name}_dimensions_unique", 0, 64)
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('releasekit.tables.stats', 'app_release_stats'));
    }
};
