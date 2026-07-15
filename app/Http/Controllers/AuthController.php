<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Settings $settings)
    {
        return view('auth.login', [
            'settings' => $settings->allPublic(),
            'seo' => Seo::defaults(['title' => 'Giriş yap - nozu.me']),
        ]);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-posta veya parola hatalı.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('profile.edit'));
    }

    public function register(Settings $settings)
    {
        return view('auth.register', [
            'settings' => $settings->allPublic(),
            'seo' => Seo::defaults(['title' => 'Kayıt ol - nozu.me']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:40', 'unique:users,username'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::query()->create($validated + ['role' => 'viewer']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('status', 'Profilin oluşturuldu.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
