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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:40', Rule::unique('users', 'username')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:500'],
            'theme' => ['required', 'in:dark,light,system'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);
        $user->update($validated);

        return back()->with('status', 'Profil güncellendi.');
    }

    public function show(string $username, Settings $settings)
    {
        $user = User::query()->where('username', $username)->firstOrFail();

        return view('profile.show', [
            'settings' => $settings->allPublic(),
            'user' => $user,
            'seo' => Seo::defaults(['title' => "{$user->name} - nozu.me"]),
        ]);
    }
}
