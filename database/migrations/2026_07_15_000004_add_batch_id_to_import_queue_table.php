<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_queue', function (Blueprint $table): void {
            $table->string('batch_id')->nullable()->after('error_message')->index();
        });
    }

    public function down(): void
    {
        Schema::table('import_queue', function (Blueprint $table): void {
            $table->dropColumn('batch_id');
        });
    }
};
