<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_lists', function (Blueprint $table): void {
            $table->dropUnique('media_lists_user_id_media_id_unique');
            $table->unique(['user_id', 'media_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('media_lists', function (Blueprint $table): void {
            $table->dropUnique('media_lists_user_id_media_id_status_unique');
            $table->unique(['user_id', 'media_id']);
        });
    }
};
