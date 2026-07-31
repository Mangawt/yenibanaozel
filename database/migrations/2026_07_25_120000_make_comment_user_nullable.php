<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table
                ->unsignedBigInteger('user_id')
                ->nullable()
                ->change();
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        /*
         * Kullanıcısı silinmiş anonim yorumlar eski şemaya dönerken
         * geçerli bir user_id alamayacağı için kaldırılır.
         */
        DB::table('comments')
            ->whereNull('user_id')
            ->delete();

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table
                ->unsignedBigInteger('user_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
