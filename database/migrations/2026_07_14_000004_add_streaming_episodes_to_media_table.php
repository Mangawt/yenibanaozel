<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('media', 'streaming_episodes')) {
            Schema::table('media', function (Blueprint $table): void {
                $table->json('streaming_episodes')->nullable()->after('external_links');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('media', 'streaming_episodes')) {
            Schema::table('media', function (Blueprint $table): void {
                $table->dropColumn('streaming_episodes');
            });
        }
    }
};
