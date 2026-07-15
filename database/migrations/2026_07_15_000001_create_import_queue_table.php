<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_queue', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32);
            $table->string('type', 16);
            $table->unsignedBigInteger('external_id');
            $table->string('status', 24)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['source', 'type', 'external_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_queue');
    }
};
