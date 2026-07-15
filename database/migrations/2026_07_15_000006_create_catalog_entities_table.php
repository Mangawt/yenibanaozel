<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('image')->nullable();
            $table->unsignedInteger('credits_count')->default(0);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('characters', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('image')->nullable();
            $table->unsignedInteger('media_count')->default(0);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('studios', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('image')->nullable();
            $table->unsignedInteger('media_count')->default(0);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('media_characters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('voice_actor_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('role')->nullable();
            $table->string('language', 40)->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'character_id']);
            $table->index(['voice_actor_id', 'media_id']);
        });

        Schema::create('media_people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('kind', 32)->default('staff');
            $table->string('role')->nullable();
            $table->string('language', 40)->nullable();
            $table->timestamps();

            $table->index(['person_id', 'media_id']);
            $table->index(['kind', 'role']);
        });

        Schema::create('media_studios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->string('role', 32)->default('studio');
            $table->timestamps();

            $table->unique(['media_id', 'studio_id', 'role']);
            $table->index(['studio_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_studios');
        Schema::dropIfExists('media_people');
        Schema::dropIfExists('media_characters');
        Schema::dropIfExists('studios');
        Schema::dropIfExists('characters');
        Schema::dropIfExists('people');
    }
};
