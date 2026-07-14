<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->json('characters')->nullable()->after('authors');
            $table->json('relations')->nullable()->after('characters');
            $table->json('recommendations')->nullable()->after('relations');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['characters', 'relations', 'recommendations']);
        });
    }
};
