<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Media;
use App\Models\MediaList;
use App\Models\Studio;
use App\Models\User;
use App\Services\UserProfileStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class ProfileStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_anime_list_sees_empty_state(): void
    {
        $user = $this->user();

        $this->get(route('profile.show', $user->username))
            ->assertOk()
            ->assertSee('Anime istatistikleri henüz oluşmadı.')
            ->assertSee('Listeye anime ekledikçe izleme alışkanlıkların burada görünecek.');
    }

    public function test_manga_entries_are_not_included_in_anime_stats(): void
    {
        $user = $this->user();
        $manga = $this->media(['type' => 'manga', 'chapters' => 20]);

        $this->list($user, $manga, ['status' => 'completed', 'progress' => 20, 'score' => 10]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertFalse($stats['has_stats']);
    }

    public function test_favorite_entries_are_not_included_in_anime_stats(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime', 'episodes' => 12]);

        $this->list($user, $anime, ['status' => 'favorite', 'progress' => 12, 'score' => 10]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertFalse($stats['has_stats']);
    }

    public function test_favorite_and_primary_status_for_same_media_are_not_double_counted(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime', 'episodes' => 12, 'duration' => 24]);

        $this->list($user, $anime, ['status' => 'favorite']);
        $this->list($user, $anime, ['status' => 'completed', 'progress' => 12]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame(1, $stats['summary']['completed_anime_count']);
        $this->assertSame(12, $stats['summary']['watched_episodes']);
    }

    public function test_completed_anime_count_is_calculated_correctly(): void
    {
        $user = $this->user();

        $this->list($user, $this->media(['type' => 'anime']), ['status' => 'completed']);
        $this->list($user, $this->media(['type' => 'anime']), ['status' => 'watching']);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame(1, $stats['summary']['completed_anime_count']);
    }

    public function test_progress_is_used_for_watched_episode_count(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime', 'episodes' => 24]);

        $this->list($user, $anime, ['status' => 'watching', 'progress' => 7]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame(7, $stats['summary']['watched_episodes']);
    }

    public function test_completed_anime_uses_episodes_when_progress_is_empty(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime', 'episodes' => 13]);

        $this->list($user, $anime, ['status' => 'completed', 'progress' => 0]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame(13, $stats['summary']['watched_episodes']);
    }

    public function test_planned_entries_do_not_contribute_to_episode_or_time_totals(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime', 'episodes' => 12, 'duration' => 24]);

        $this->list($user, $anime, ['status' => 'planned', 'progress' => 5]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame(0, $stats['summary']['watched_episodes']);
        $this->assertSame(0, $stats['summary']['watch_minutes']);
    }

    public function test_missing_duration_does_not_break_watch_time_calculation(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime', 'episodes' => 10, 'duration' => null]);

        $this->list($user, $anime, ['status' => 'watching', 'progress' => 4]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame(4, $stats['summary']['watched_episodes']);
        $this->assertSame(0, $stats['summary']['watch_minutes']);
        $this->assertSame('0 dakika', $stats['summary']['watch_time_label']);
    }

    public function test_genre_distribution_is_sorted_by_count(): void
    {
        $user = $this->user();

        $this->list($user, $this->media(['genres' => ['Aksiyon', 'Dram']]), ['status' => 'completed']);
        $this->list($user, $this->media(['genres' => ['Aksiyon']]), ['status' => 'watching']);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame('Aksiyon', $stats['spectrum'][0]['name']);
        $this->assertSame(100, $stats['spectrum'][0]['percent']);
        $this->assertSame('Dram', $stats['spectrum'][1]['name']);
    }

    public function test_genre_distribution_is_limited_to_six_items(): void
    {
        $user = $this->user();

        $this->list($user, $this->media([
            'genres' => ['Aksiyon', 'Dram', 'Fantastik', 'Komedi', 'Macera', 'Gizem', 'Romantik'],
        ]), ['status' => 'completed']);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertCount(6, $stats['spectrum']);
    }

    public function test_average_score_uses_only_positive_scores(): void
    {
        $user = $this->user();

        $this->list($user, $this->media(), ['status' => 'completed', 'score' => 8]);
        $this->list($user, $this->media(), ['status' => 'watching', 'score' => 0]);
        $this->list($user, $this->media(), ['status' => 'paused', 'score' => 10]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame('9,0', $stats['summary']['average_score']);
    }

    public function test_anime_comment_count_is_calculated_correctly(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime']);
        $manga = $this->media(['type' => 'manga']);

        $this->list($user, $anime, ['status' => 'watching']);
        Comment::query()->create(['user_id' => $user->id, 'media_id' => $anime->id, 'body' => 'Anime yorumu']);
        Comment::query()->create(['user_id' => $user->id, 'media_id' => $manga->id, 'body' => 'Manga yorumu']);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame(1, $stats['social']['anime_comments_count']);
    }

    public function test_positive_comment_score_is_calculated_correctly(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime']);

        $this->list($user, $anime, ['status' => 'watching']);
        Comment::query()->create(['user_id' => $user->id, 'media_id' => $anime->id, 'body' => 'İyi', 'score' => 4]);
        Comment::query()->create(['user_id' => $user->id, 'media_id' => $anime->id, 'body' => 'Kötü', 'score' => -3]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame(4, $stats['social']['positive_comment_score']);
    }

    public function test_profile_page_still_shows_existing_basic_profile_sections(): void
    {
        $user = $this->user();

        $this->get(route('profile.show', $user->username))
            ->assertOk()
            ->assertSee('@'.$user->username)
            ->assertSee('Favori animeler')
            ->assertSee('Favori mangalar')
            ->assertSee('Sosyal');
    }

    public function test_owner_and_guest_can_see_public_profile_stats(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime', 'episodes' => 12]);

        $this->list($user, $anime, ['status' => 'completed']);

        $this->get(route('profile.show', $user->username))
            ->assertOk()
            ->assertSee('Anime İstatistikleri')
            ->assertSee('Tamamlanan anime');

        $this->actingAs($user)
            ->get(route('profile.show', $user->username))
            ->assertOk()
            ->assertSee('Anime İstatistikleri')
            ->assertSee('Tamamlanan anime');
    }

    public function test_service_calculates_directly_when_cache_is_unavailable(): void
    {
        $user = $this->user();
        $anime = $this->media(['type' => 'anime', 'episodes' => 6]);

        $this->list($user, $anime, ['status' => 'completed']);

        Cache::shouldReceive('remember')
            ->once()
            ->andThrow(new RuntimeException('cache unavailable'));

        $stats = app(UserProfileStatsService::class)->forUser($user);

        $this->assertTrue($stats['has_stats']);
        $this->assertSame(1, $stats['summary']['completed_anime_count']);
    }

    public function test_identity_uses_format_studio_and_recent_activity(): void
    {
        $user = $this->user();
        $studio = Studio::query()->create(['slug' => 'nozu-studio', 'name' => 'Nozu Studio']);
        $anime = $this->media(['type' => 'anime', 'format' => 'TV', 'genres' => ['Aksiyon']]);
        $anime->normalizedStudios()->attach($studio->id, ['role' => 'studio']);

        $this->list($user, $anime, ['status' => 'watching', 'updated_at' => now()]);

        $stats = app(UserProfileStatsService::class)->calculate($user);

        $this->assertSame('Aksiyon', $stats['identity']['dominant_genre']);
        $this->assertSame('TV', $stats['identity']['top_format']);
        $this->assertSame('Nozu Studio', $stats['identity']['top_studio']);
        $this->assertSame(1, $stats['identity']['recent_activity_count']);
    }

    private function user(string $email = 'profile@example.com'): User
    {
        return User::factory()->create([
            'name' => 'Nozu Kullanıcı',
            'username' => str_replace('@example.com', '', $email),
            'email' => $email,
            'password' => Hash::make('password'),
            'bio' => 'Anime izlemeyi sever.',
        ]);
    }

    private function media(array $overrides = []): Media
    {
        return Media::query()->create(array_replace([
            'type' => 'anime',
            'slug' => 'profile-media-'.strtolower(fake()->bothify('????-####')),
            'title' => 'Profil Test Anime',
            'cover_image' => 'https://nozu.me/test.jpg',
            'episodes' => 12,
            'duration' => 24,
            'format' => 'TV',
            'genres' => ['Aksiyon'],
        ], $overrides));
    }

    private function list(User $user, Media $media, array $overrides = []): MediaList
    {
        return MediaList::query()->create(array_replace([
            'user_id' => $user->id,
            'media_id' => $media->id,
            'status' => 'watching',
            'progress' => 0,
            'score' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
