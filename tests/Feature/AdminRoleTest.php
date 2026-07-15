<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect();
    }

    public function test_normal_user_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->user('member'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->user('admin'))
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_viewer_can_view_but_cannot_write(): void
    {
        $media = Media::query()->create([
            'type' => 'anime',
            'slug' => 'anime-test-1',
            'title' => 'Test Anime',
        ]);

        $this->actingAs($this->user('viewer'))
            ->get('/admin/dashboard')
            ->assertOk();

        $this->actingAs($this->user('viewer'))
            ->put("/admin/media/{$media->id}", ['title' => 'Changed'])
            ->assertForbidden();

        $this->assertSame('Test Anime', $media->refresh()->title);
    }

    public function test_login_rate_limit_works(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('correct-password'),
            'role' => 'admin',
        ]);

        for ($index = 0; $index < 5; $index++) {
            $this->post('/admin/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = $this->user('admin');

        $this->actingAs($user)
            ->post('/admin/logout')
            ->assertRedirect('/admin');

        $this->assertGuest();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'username' => fake()->unique()->userName(),
        ]);
    }
}
