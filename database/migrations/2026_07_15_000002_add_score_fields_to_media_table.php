<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->unsignedSmallInteger('mean_score')->nullable()->after('average_score');
            $table->unsignedInteger('favourites')->nullable()->after('popularity');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['mean_score', 'favourites']);
        });
    }
};
