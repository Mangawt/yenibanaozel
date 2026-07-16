<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Settings $settings)
    {
        return view('profile.edit', [
            'settings' => $settings->allPublic(),
            'user' => Auth::user(),
            'seo' => Seo::defaults(['title' => 'Profilim - nozu.me']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'bio' => ['nullable', 'string', 'max:500'],
            'theme' => ['required', 'in:dark,light,system'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'social_links.instagram' => ['nullable', 'url', 'max:180'],
            'social_links.facebook' => ['nullable', 'url', 'max:180'],
            'social_links.discord' => ['nullable', 'string', 'max:80'],
            'social_links.x' => ['nullable', 'url', 'max:180'],
            'social_links.youtube' => ['nullable', 'url', 'max:180'],
            'social_links.website' => ['nullable', 'url', 'max:180'],
        ];

        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            $rules['username'] = ['required', 'alpha_dash', 'min:3', 'max:40', Rule::unique('users', 'username')->ignore($user->id)];
        }

        $validated = $request->validate($rules);

        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            unset($validated['username']);
        }

        $validated['name'] = $user->username ?: $user->name;
        $validated['social_links'] = collect($validated['social_links'] ?? [])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter()
            ->all();

        if ($request->hasFile('avatar')) {
            $validated['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);
        $user->update($validated);

        return back()->with('status', 'Profil güncellendi.');
    }

    public function show(string $username, Settings $settings)
    {
        $user = User::query()
            ->where('username', $username)
            ->withCount(['followers', 'following'])
            ->firstOrFail();
        $favoriteAnimeCount = $user->favoriteMedia()->wherePivot('status', 'favorite')->where('media.type', 'anime')->count();
        $favoriteMangaCount = $user->favoriteMedia()->wherePivot('status', 'favorite')->where('media.type', 'manga')->count();
        $activityFeed = $user->mediaList()->with('media')->latest()->limit(10)->get()
            ->map(fn ($activity) => [
                'media' => $activity->media,
                'status' => $activity->status,
                'label' => null,
                'created_at' => $activity->updated_at,
            ])
            ->concat($user->comments()->with('media')->latest()->limit(10)->get()
                ->map(fn ($comment) => [
                    'media' => $comment->media,
                    'status' => null,
                    'label' => 'Yorum yaptı',
                    'created_at' => $comment->created_at,
                ]))
            ->filter(fn ($activity) => $activity['media'])
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        return view('profile.show', [
            'settings' => $settings->allPublic(),
            'user' => $user,
            'favoriteAnimeCount' => $favoriteAnimeCount,
            'favoriteMangaCount' => $favoriteMangaCount,
            'favoritesAnime' => $user->favoriteMedia()->wherePivot('status', 'favorite')->where('media.type', 'anime')->latest('media_lists.updated_at')->limit(4)->get(),
            'favoritesManga' => $user->favoriteMedia()->wherePivot('status', 'favorite')->where('media.type', 'manga')->latest('media_lists.updated_at')->limit(4)->get(),
            'watchList' => $user->mediaList()->with('media')->where('status', '!=', 'favorite')->latest()->limit(10)->get(),
            'activities' => $activityFeed,
            'isFollowing' => auth()->check() ? auth()->user()->following()->whereKey($user->id)->exists() : false,
            'seo' => Seo::defaults(['title' => '@'.$user->username.' - nozu.me']),
        ]);
    }

    public function list(Settings $settings)
    {
        $user = Auth::user();
        $status = $this->validListStatus(request('status'));
        $items = $this->listQuery($user, request('q'), $status)
            ->paginate(18)
            ->withQueryString();

        return view('profile.list', [
            'settings' => $settings->allPublic(),
            'items' => $items,
            'owner' => $user,
            'query' => request('q', ''),
            'activeStatus' => $status,
            'shareUrl' => route('profile.public-list', $user->username),
            'isOwner' => true,
            'seo' => Seo::defaults(['title' => 'Okuma listem - nozu.me']),
        ]);
    }

    public function publicList(string $username, Settings $settings)
    {
        $user = User::query()->where('username', $username)->firstOrFail();
        $status = $this->validListStatus(request('status'));
        $items = $this->listQuery($user, request('q'), $status)
            ->paginate(18)
            ->withQueryString();

        return view('profile.list', [
            'settings' => $settings->allPublic(),
            'items' => $items,
            'owner' => $user,
            'query' => request('q', ''),
            'activeStatus' => $status,
            'shareUrl' => route('profile.public-list', $user->username),
            'isOwner' => auth()->check() && auth()->id() === $user->id,
            'seo' => Seo::defaults(['title' => '@'.$user->username.' izleme listesi - nozu.me']),
        ]);
    }

    private function listQuery(User $user, ?string $search = null, ?string $status = null)
    {
        return $user->mediaList()
            ->with('media')
            ->where('status', '!=', 'favorite')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, function ($query, string $search): void {
                $query->whereHas('media', function ($mediaQuery) use ($search): void {
                    $mediaQuery->where('title', 'like', '%'.$search.'%')
                        ->orWhere('title_english', 'like', '%'.$search.'%')
                        ->orWhere('title_native', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->orderByDesc('id');
    }

    private function validListStatus(?string $status): ?string
    {
        return in_array($status, ['watching', 'reading', 'completed', 'dropped', 'planned'], true) ? $status : null;
    }

    public function followers(string $username, Settings $settings)
    {
        $user = User::query()->where('username', $username)->firstOrFail();

        return view('profile.connections', [
            'settings' => $settings->allPublic(),
            'user' => $user,
            'title' => 'Takipçiler',
            'people' => $user->followers()->paginate(36),
            'seo' => Seo::defaults(['title' => '@'.$user->username.' takipçileri - nozu.me']),
        ]);
    }

    public function following(string $username, Settings $settings)
    {
        $user = User::query()->where('username', $username)->firstOrFail();

        return view('profile.connections', [
            'settings' => $settings->allPublic(),
            'user' => $user,
            'title' => 'Takip edilenler',
            'people' => $user->following()->paginate(36),
            'seo' => Seo::defaults(['title' => '@'.$user->username.' takip ettikleri - nozu.me']),
        ]);
    }
}
