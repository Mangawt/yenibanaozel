<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table
                ->string('google_id', 255)
                ->nullable()
                ->unique()
                ->after('email');

            $table
                ->string('google_avatar', 2048)
                ->nullable()
                ->after('google_id');

            $table
                ->string('auth_provider', 30)
                ->default('password')
                ->after('google_avatar');

            $table->index('auth_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['auth_provider']);
            $table->dropUnique(['google_id']);

            $table->dropColumn([
                'google_id',
                'google_avatar',
                'auth_provider',
            ]);
        });
    }
};
