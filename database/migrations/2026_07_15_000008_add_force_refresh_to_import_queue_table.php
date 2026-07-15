<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_queue', function (Blueprint $table): void {
            $table->boolean('force_refresh')->default(false)->after('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_queue', function (Blueprint $table): void {
            $table->dropColumn('force_refresh');
        });
    }
};
