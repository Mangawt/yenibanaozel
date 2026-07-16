<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('description_original_hash', 64)->nullable()->after('description_original');
            $table->string('translation_provider', 32)->nullable()->after('description_original_hash');
            $table->timestamp('translated_at')->nullable()->after('translation_provider');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['description_original_hash', 'translation_provider', 'translated_at']);
        });
    }
};
