<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('role', 32)->default('user')->after('password');
            $table->string('avatar_path')->nullable()->after('role');
            $table->text('bio')->nullable()->after('avatar_path');
            $table->string('theme', 16)->default('system')->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['username', 'role', 'avatar_path', 'bio', 'theme']);
        });
    }
};
