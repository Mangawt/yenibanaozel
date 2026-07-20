<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ChromeExtensionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token_once(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Nozu Chrome Extension',
        ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonMissingPath('data.user.role')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'avatar']]]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Nozu Chrome Extension',
        ]);
    }

    public function test_wrong_password_and_missing_user_return_same_message(): void
    {
        $user = $this->user();

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
            'device_name' => 'Nozu Chrome Extension',
        ]);

        $missingUser = $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
            'device_name' => 'Nozu Chrome Extension',
        ]);

        $wrongPassword->assertStatus(422)->assertJsonPath('message', 'E-posta veya şifre hatalı.');
        $missingUser->assertStatus(422)->assertJsonPath('message', 'E-posta veya şifre hatalı.');
    }

    public function test_login_rate_limit_uses_email_and_ip(): void
    {
        RateLimiter::clear('public-api:127.0.0.1');
        $user = $this->user();

        for ($index = 0; $index < 5; $index++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong',
                'device_name' => 'Nozu Chrome Extension',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
            'device_name' => 'Nozu Chrome Extension',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false);
    }

    public function test_me_requires_bearer_token(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->user();
        $token = $user->createToken('Expired', ['extension:read'], now()->subMinute())->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_read_ability_is_required_for_me(): void
    {
        $user = $this->user();

        $this->withBearerToken($user, ['extension:list-write'])
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_list_write_ability_is_required_for_list_write(): void
    {
        $user = $this->user();
        $media = $this->media();

        $this->withBearerToken($user, ['extension:read'])
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'watching',
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_user_can_create_list_entry(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'anime']);

        $this->withBearerToken($user)
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'watching',
                'progress' => 3,
                'score' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'watching')
            ->assertJsonPath('data.progress', 3)
            ->assertJsonPath('data.score', 8);

        $this->assertDatabaseHas('media_lists', [
            'user_id' => $user->id,
            'media_id' => $media->id,
            'status' => 'watching',
            'progress' => 3,
            'score' => 8,
        ]);
    }

    public function test_user_can_update_list_entry_without_creating_duplicate_nonfavorite_status(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'manga']);

        MediaList::query()->create([
            'user_id' => $user->id,
            'media_id' => $media->id,
            'status' => 'reading',
            'progress' => 2,
            'score' => 6,
        ]);

        $this->withBearerToken($user)
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'completed',
                'progress' => 42,
                'score' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress', 42);

        $this->assertDatabaseMissing('media_lists', [
            'user_id' => $user->id,
            'media_id' => $media->id,
            'status' => 'reading',
        ]);
        $this->assertSame(1, MediaList::query()->where('user_id', $user->id)->where('media_id', $media->id)->where('status', '!=', 'favorite')->count());
    }

    public function test_favorite_and_primary_status_can_exist_together(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'anime']);

        $this->withBearerToken($user)
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'favorite',
            ])
            ->assertOk();

        $this->withBearerToken($user)
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'watching',
                'progress' => 5,
                'score' => 7,
            ])
            ->assertOk();

        $this->assertDatabaseHas('media_lists', [
            'user_id' => $user->id,
            'media_id' => $media->id,
            'status' => 'favorite',
        ]);
        $this->assertDatabaseHas('media_lists', [
            'user_id' => $user->id,
            'media_id' => $media->id,
            'status' => 'watching',
            'progress' => 5,
            'score' => 7,
        ]);
    }

    public function test_primary_status_change_keeps_favorite(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'anime']);

        MediaList::query()->create(['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'favorite']);
        MediaList::query()->create(['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'watching']);

        $this->withBearerToken($user)
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'completed',
            ])
            ->assertOk();

        $this->assertDatabaseHas('media_lists', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'favorite']);
        $this->assertDatabaseHas('media_lists', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'completed']);
        $this->assertDatabaseMissing('media_lists', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'watching']);
    }

    public function test_user_cannot_access_another_users_list_state(): void
    {
        $owner = $this->user('owner@example.com');
        $other = $this->user('other@example.com');
        $media = $this->media();

        MediaList::query()->create([
            'user_id' => $owner->id,
            'media_id' => $media->id,
            'status' => 'watching',
        ]);

        $this->withBearerToken($other)
            ->getJson("/api/v1/media/{$media->id}/my-list")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_user_cannot_update_another_users_record(): void
    {
        $owner = $this->user('owner@example.com');
        $other = $this->user('other@example.com');
        $media = $this->media();

        MediaList::query()->create([
            'user_id' => $owner->id,
            'media_id' => $media->id,
            'status' => 'watching',
            'progress' => 7,
        ]);

        $this->withBearerToken($other)
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'watching',
                'progress' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseHas('media_lists', [
            'user_id' => $owner->id,
            'media_id' => $media->id,
            'status' => 'watching',
            'progress' => 7,
        ]);
    }

    public function test_user_cannot_delete_another_users_record(): void
    {
        $owner = $this->user('owner@example.com');
        $other = $this->user('other@example.com');
        $media = $this->media();

        MediaList::query()->create([
            'user_id' => $owner->id,
            'media_id' => $media->id,
            'status' => 'watching',
        ]);

        $this->withBearerToken($other)
            ->deleteJson("/api/v1/me/list/{$media->id}/watching")
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        $this->assertDatabaseHas('media_lists', [
            'user_id' => $owner->id,
            'media_id' => $media->id,
            'status' => 'watching',
        ]);
    }

    public function test_logout_invalidates_current_token_only(): void
    {
        $user = $this->user();
        $currentToken = $user->createToken('Current', ['extension:read'])->plainTextToken;
        $otherToken = $user->createToken('Other', ['extension:read'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.logged_out', true);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_delete_status_removes_only_requested_status(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'anime']);

        MediaList::query()->create(['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'favorite']);
        MediaList::query()->create(['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'watching']);

        $this->withBearerToken($user)
            ->deleteJson("/api/v1/me/list/{$media->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('media_lists', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'favorite']);
        $this->assertDatabaseHas('media_lists', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'watching']);
    }

    public function test_legacy_delete_removes_primary_status_but_keeps_favorite(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'anime']);

        MediaList::query()->create(['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'favorite']);
        MediaList::query()->create(['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'watching']);

        $this->withBearerToken($user)
            ->deleteJson("/api/v1/me/list/{$media->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseHas('media_lists', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'favorite']);
        $this->assertDatabaseMissing('media_lists', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'watching']);
    }

    public function test_my_list_returns_primary_status_and_favorite_flag(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'anime']);

        MediaList::query()->create(['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'favorite']);
        MediaList::query()->create([
            'user_id' => $user->id,
            'media_id' => $media->id,
            'status' => 'watching',
            'progress' => 10,
            'score' => 8,
        ]);

        $this->withBearerToken($user)
            ->getJson("/api/v1/media/{$media->id}/my-list")
            ->assertOk()
            ->assertJsonPath('data.status', 'watching')
            ->assertJsonPath('data.progress', 10)
            ->assertJsonPath('data.score', 8)
            ->assertJsonPath('data.is_favorite', true);
    }

    public function test_my_list_returns_null_primary_status_when_only_favorite_exists(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'anime']);

        MediaList::query()->create(['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'favorite']);

        $this->withBearerToken($user)
            ->getJson("/api/v1/media/{$media->id}/my-list")
            ->assertOk()
            ->assertJsonPath('data.status', null)
            ->assertJsonPath('data.progress', null)
            ->assertJsonPath('data.score', null)
            ->assertJsonPath('data.is_favorite', true);
    }

    public function test_validation_errors_are_standard_json(): void
    {
        $user = $this->user();
        $media = $this->media(['type' => 'manga']);

        $this->withBearerToken($user)
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'watching',
                'progress' => -1,
                'score' => 11,
                'user_id' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $user = $this->user();

        $this->withBearerToken($user)
            ->getJson('/api/v1/me/list?type=novel&per_page=51&page=0')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_write_and_delete_rate_limits_are_enforced(): void
    {
        RateLimiter::clear('public-api:127.0.0.1');
        $user = $this->user();
        $media = $this->media();
        $token = $user->createToken('Nozu Chrome Extension', ['extension:list-write'])->plainTextToken;

        for ($index = 0; $index < 30; $index++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/me/list', [
                    'media_id' => $media->id,
                    'status' => 'watching',
                ])
                ->assertOk();
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/list', [
                'media_id' => $media->id,
                'status' => 'watching',
            ])
            ->assertStatus(429);

        RateLimiter::clear('public-api:127.0.0.1');
        $deleteToken = $user->createToken('Nozu Chrome Extension Delete', ['extension:list-write'])->plainTextToken;

        for ($index = 0; $index < 10; $index++) {
            $this->withHeader('Authorization', 'Bearer '.$deleteToken)
                ->deleteJson("/api/v1/me/list/{$media->id}/watching")
                ->assertOk();
        }

        $this->withHeader('Authorization', 'Bearer '.$deleteToken)
            ->deleteJson("/api/v1/me/list/{$media->id}/watching")
            ->assertStatus(429);
    }

    private function user(string $email = 'user@example.com'): User
    {
        return User::factory()->create([
            'name' => 'Nozu User',
            'username' => str_replace('@example.com', '', $email),
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
    }

    private function withBearerToken(User $user, array $abilities = ['extension:read', 'extension:list-write']): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('Nozu Chrome Extension', $abilities)->plainTextToken);
    }

    private function media(array $overrides = []): Media
    {
        return Media::query()->create(array_replace([
            'type' => 'anime',
            'slug' => 'test-media-'.strtolower(fake()->bothify('????-###')),
            'title' => 'Test Media',
            'cover_image' => 'https://nozu.me/storage/test.jpg',
        ], $overrides));
    }
}
