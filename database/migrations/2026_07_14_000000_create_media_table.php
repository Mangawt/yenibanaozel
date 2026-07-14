<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16);
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('title_english')->nullable();
            $table->string('title_native')->nullable();
            $table->text('description')->nullable();
            $table->text('description_original')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('format')->nullable();
            $table->string('status')->nullable();
            $table->unsignedSmallInteger('average_score')->nullable();
            $table->unsignedInteger('popularity')->nullable();
            $table->unsignedSmallInteger('episodes')->nullable();
            $table->unsignedSmallInteger('chapters')->nullable();
            $table->unsignedSmallInteger('volumes')->nullable();
            $table->unsignedSmallInteger('duration')->nullable();
            $table->string('season', 32)->nullable();
            $table->unsignedSmallInteger('season_year')->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->json('genres')->nullable();
            $table->json('studios')->nullable();
            $table->json('authors')->nullable();
            $table->json('source_ids')->nullable();
            $table->boolean('is_adult')->default(false);
            $table->timestamps();

            $table->index(['type', 'average_score']);
            $table->index(['type', 'popularity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
