<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            if (! Schema::hasColumn('comments', 'is_spoiler')) {
                $table->boolean('is_spoiler')
                    ->default(false)
                    ->after('body');
            }
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'reportable_type', 'reportable_id'],
                'reports_user_reportable_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex('reports_user_reportable_index');
        });

        Schema::table('comments', function (Blueprint $table): void {
            if (Schema::hasColumn('comments', 'is_spoiler')) {
                $table->dropColumn('is_spoiler');
            }
        });
    }
};
