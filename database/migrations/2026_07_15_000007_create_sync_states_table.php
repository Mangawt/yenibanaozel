<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_states', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32)->default('anilist');
            $table->string('type', 16);
            $table->string('mode', 40);
            $table->json('filters')->nullable();
            $table->string('status', 32)->default('idle');
            $table->unsignedInteger('current_page')->default(1);
            $table->unsignedInteger('last_successful_page')->default(0);
            $table->unsignedBigInteger('last_external_id')->nullable();
            $table->unsignedSmallInteger('requests_in_window')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('existing_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('lock_owner')->nullable();
            $table->timestamps();

            $table->index(['source', 'type', 'mode']);
            $table->index(['status', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
