<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_partition_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_state_id')->constrained('sync_states')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('format', 40);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('current_page')->default(1);
            $table->unsignedInteger('last_successful_page')->default(0);
            $table->unsignedInteger('last_page')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['sync_state_id', 'year', 'format'], 'sync_partition_unique');
            $table->index(['sync_state_id', 'status']);
            $table->index(['sync_state_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_partition_states');
    }
};
