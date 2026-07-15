<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_states', function (Blueprint $table): void {
            $table->timestamp('window_started_at')->nullable()->after('requests_in_window');
        });
    }

    public function down(): void
    {
        Schema::table('sync_states', function (Blueprint $table): void {
            $table->dropColumn('window_started_at');
        });
    }
};
