<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('status', 32)->default('favorite');
            $table->timestamps();
            $table->unique(['user_id', 'media_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->integer('score')->default(0);
            $table->timestamps();
            $table->index(['media_id', 'parent_id', 'created_at']);
        });

        Schema::create('comment_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();
            $table->unique(['user_id', 'comment_id']);
        });

        Schema::create('user_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_id', 'following_id']);
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('reportable');
            $table->string('reason', 80)->default('other');
            $table->text('details')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('user_follows');
        Schema::dropIfExists('comment_votes');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('media_lists');
    }
};
