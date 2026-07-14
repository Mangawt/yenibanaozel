<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('cover_image_original')->nullable()->after('cover_image');
            $table->string('banner_image_original')->nullable()->after('banner_image');
            $table->string('country_of_origin', 8)->nullable()->after('duration');
            $table->string('source')->nullable()->after('country_of_origin');
            $table->string('hashtag')->nullable()->after('source');
            $table->string('site_url')->nullable()->after('hashtag');
            $table->date('start_date')->nullable()->after('start_year');
            $table->date('end_date')->nullable()->after('start_date');
            $table->json('synonyms')->nullable()->after('authors');
            $table->json('tags')->nullable()->after('recommendations');
            $table->json('rankings')->nullable()->after('tags');
            $table->json('staff')->nullable()->after('rankings');
            $table->json('producers')->nullable()->after('staff');
            $table->json('external_links')->nullable()->after('producers');
            $table->json('streaming_episodes')->nullable()->after('external_links');
            $table->json('trailer')->nullable()->after('streaming_episodes');
            $table->json('next_airing_episode')->nullable()->after('trailer');
            $table->json('stats')->nullable()->after('next_airing_episode');
            $table->json('raw_payload')->nullable()->after('stats');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn([
                'cover_image_original',
                'banner_image_original',
                'country_of_origin',
                'source',
                'hashtag',
                'site_url',
                'start_date',
                'end_date',
                'synonyms',
                'tags',
                'rankings',
                'staff',
                'producers',
                'external_links',
                'streaming_episodes',
                'trailer',
                'next_airing_episode',
                'stats',
                'raw_payload',
            ]);
        });
    }
};
