<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            if (! Schema::hasColumn('media', 'last_external_sync_at')) {
                $table->timestamp('last_external_sync_at')->nullable()->after('translated_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            if (Schema::hasColumn('media', 'last_external_sync_at')) {
                $table->dropColumn('last_external_sync_at');
            }
        });
    }
};
