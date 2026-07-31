<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('actor_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('type', 40);
                $table->string('title', 160);
                $table->text('body')->nullable();

                $table->string('target_type', 40)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('target_slug')->nullable();

                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();

                $table->timestamps();

                $table->index([
                    'user_id',
                    'read_at',
                    'created_at',
                ]);

                $table->index([
                    'target_type',
                    'target_id',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
