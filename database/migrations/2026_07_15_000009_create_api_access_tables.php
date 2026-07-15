<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('contact_email');
            $table->string('website_url')->nullable();
            $table->string('application_type', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('plan', 32)->default('free');
            $table->json('allowed_domains')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->json('custom_limits')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('attribution_required')->default(true);
            $table->boolean('commercial_use_allowed')->default(false);
            $table->boolean('competitor_use_allowed')->default(false);
            $table->boolean('auto_suspend_enabled')->default(true);
            $table->unsignedInteger('abuse_score')->default(0);
            $table->timestamp('suspended_until')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 32)->nullable();
            $table->string('terms_ip', 64)->nullable();
            $table->text('license_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'plan']);
            $table->index('contact_email');
        });

        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 32)->index();
            $table->string('key_hash', 128)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['api_client_id', 'status']);
        });

        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 12);
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->string('ip_address', 64)->nullable();
            $table->string('origin')->nullable();
            $table->string('referer')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->unsignedInteger('response_size')->default(0);
            $table->unsignedSmallInteger('request_cost')->default(1);
            $table->timestamps();

            $table->index(['api_client_id', 'created_at']);
            $table->index(['status_code', 'created_at']);
        });

        Schema::create('api_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('application_name');
            $table->string('website_url')->nullable();
            $table->string('application_type', 80)->nullable();
            $table->string('expected_daily_requests', 80)->nullable();
            $table->boolean('commercial')->default(false);
            $table->boolean('anime_database')->default(false);
            $table->boolean('competitor_service')->default(false);
            $table->boolean('will_attribute')->default(false);
            $table->text('purpose');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('assigned_plan', 32)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_applications');
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('api_clients');
    }
};
