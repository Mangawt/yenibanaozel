<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            if (! Schema::hasColumn('media', 'turkish_purchase_url')) {
                $table->string('turkish_purchase_url', 500)->nullable()->after('site_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            if (Schema::hasColumn('media', 'turkish_purchase_url')) {
                $table->dropColumn('turkish_purchase_url');
            }
        });
    }
};
