<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_lists', function (Blueprint $table): void {
            if (! Schema::hasColumn('media_lists', 'progress')) {
                $table->unsignedInteger('progress')->default(0)->after('status');
            }

            if (! Schema::hasColumn('media_lists', 'score')) {
                $table->unsignedTinyInteger('score')->nullable()->after('progress');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media_lists', function (Blueprint $table): void {
            if (Schema::hasColumn('media_lists', 'score')) {
                $table->dropColumn('score');
            }

            if (Schema::hasColumn('media_lists', 'progress')) {
                $table->dropColumn('progress');
            }
        });
    }
};
