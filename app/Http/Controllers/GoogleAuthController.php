<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes([
                'openid',
                'email',
                'profile',
            ])
            ->with([
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function callback(
        Request $request,
    ): RedirectResponse {
        try {
            $googleUser = Socialite::driver('google')
                ->user();
        } catch (Throwable $exception) {
            Log::channel('security')->warning(
                'Google web authentication failed.',
                [
                    'ip' => $request->ip(),
                    'exception' => $exception->getMessage(),
                ],
            );

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' =>
                        'Google ile giriş tamamlanamadı. Lütfen tekrar dene.',
                ]);
        }

        $googleId = trim(
            (string) $googleUser->getId(),
        );

        $email = mb_strtolower(
            trim((string) $googleUser->getEmail()),
        );

        $name = trim(
            (string) $googleUser->getName(),
        );

        $avatar = trim(
            (string) $googleUser->getAvatar(),
        );

        $rawGoogleData = is_array($googleUser->user)
            ? $googleUser->user
            : [];

        $emailVerified = filter_var(
            $rawGoogleData['verified_email']
                ?? $rawGoogleData['email_verified']
                ?? false,
            FILTER_VALIDATE_BOOL,
        );

        if (
            $googleId === '' ||
            $email === '' ||
            ! $emailVerified
        ) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' =>
                        'Google hesabından doğrulanmış e-posta bilgisi alınamadı.',
                ]);
        }

        try {
            $user = DB::transaction(
                function () use (
                    $googleId,
                    $email,
                    $name,
                    $avatar,
                ): User {
                    /*
                     * Daha önce Google hesabı bağlanmışsa
                     * doğrudan aynı kullanıcıyı getirir.
                     */
                    $user = User::query()
                        ->where('google_id', $googleId)
                        ->first();

                    if ($user) {
                        $user->forceFill([
                            'google_avatar' =>
                                $avatar !== ''
                                    ? $avatar
                                    : $user->google_avatar,

                            'email_verified_at' =>
                                $user->email_verified_at
                                    ?? now(),
                        ])->save();

                        return $user;
                    }

                    /*
                     * Aynı doğrulanmış e-postayla klasik Nozu
                     * hesabı varsa Google hesabını ona bağlar.
                     */
                    $user = User::query()
                        ->whereRaw(
                            'LOWER(email) = ?',
                            [$email],
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($user) {
                        if (
                            filled($user->google_id) &&
                            $user->google_id !== $googleId
                        ) {
                            throw new \RuntimeException(
                                'Bu e-posta başka bir Google hesabıyla bağlantılı.',
                            );
                        }

                        $user->forceFill([
                            'google_id' => $googleId,

                            'google_avatar' =>
                                $avatar !== ''
                                    ? $avatar
                                    : $user->google_avatar,

                            'auth_provider' =>
                                $user->auth_provider === 'password'
                                    ? 'password_google'
                                    : 'google',

                            'email_verified_at' =>
                                $user->email_verified_at
                                    ?? now(),
                        ])->save();

                        return $user;
                    }

                    /*
                     * Hiç Nozu hesabı yoksa otomatik oluşturur.
                     */
                    return User::query()->create([
                        'name' =>
                            $name !== ''
                                ? mb_substr($name, 0, 80)
                                : 'Nozu Kullanıcısı',

                        'username' =>
                            $this->uniqueUsername(
                                $email,
                                $name,
                            ),

                        'email' => $email,

                        /*
                         * Google kullanıcısı bu rastgele şifreyi
                         * bilmez ve klasik şifreyle giriş yapamaz.
                         */
                        'password' => Str::random(64),

                        'role' => 'user',
                        'theme' => 'system',

                        'google_id' => $googleId,

                        'google_avatar' =>
                            $avatar !== ''
                                ? $avatar
                                : null,

                        'auth_provider' => 'google',
                        'email_verified_at' => now(),
                    ]);
                },
                attempts: 3,
            );
        } catch (Throwable $exception) {
            Log::channel('security')->error(
                'Google web user creation or linking failed.',
                [
                    'email_hash' => hash(
                        'sha256',
                        $email,
                    ),
                    'ip' => $request->ip(),
                    'exception' => $exception->getMessage(),
                ],
            );

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' =>
                        'Google hesabı Nozu hesabına bağlanamadı.',
                ]);
        }

        Auth::login(
            $user,
            true,
        );

        $request->session()->regenerate();

        Log::channel('security')->info(
            'Nozu Google web login completed.',
            [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ],
        );

        return redirect()
            ->intended(
                route('profile.edit'),
            )
            ->with(
                'status',
                'Google hesabınla giriş yaptın.',
            );
    }

    public function redirectAccountDeletion(
        Request $request,
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (
            ! filled($user->google_id) ||
            ! in_array(
                $user->auth_provider,
                [
                    'google',
                    'password_google',
                ],
                true,
            )
        ) {
            return redirect()
                ->route('account.settings')
                ->withErrors([
                    'google' =>
                        'Bu hesaba bağlı bir Google hesabı bulunmuyor.',
                ]);
        }

        $request->session()->put(
            'google_account_deletion_user_id',
            (int) $user->id,
        );

        return Socialite::driver('google')
            ->redirectUrl(
                route('account.google.callback'),
            )
            ->scopes([
                'openid',
                'email',
                'profile',
            ])
            ->with([
                'prompt' => 'select_account',
                'max_age' => 0,
            ])
            ->redirect();
    }

    public function callbackAccountDeletion(
        Request $request,
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $expectedUserId = (int) $request->session()->pull(
            'google_account_deletion_user_id',
            0,
        );

        if ($expectedUserId !== (int) $user->id) {
            return redirect()
                ->route('account.settings')
                ->withErrors([
                    'google' =>
                        'Google doğrulama oturumu geçersiz veya süresi dolmuş.',
                ]);
        }

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(
                    route('account.google.callback'),
                )
                ->user();
        } catch (Throwable $exception) {
            Log::channel('security')->warning(
                'Google account deletion verification failed.',
                [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                    'exception' => $exception->getMessage(),
                ],
            );

            return redirect()
                ->route('account.settings')
                ->withErrors([
                    'google' =>
                        'Google doğrulaması tamamlanamadı. Lütfen tekrar dene.',
                ]);
        }

        $googleId = trim(
            (string) $googleUser->getId(),
        );

        $email = mb_strtolower(
            trim((string) $googleUser->getEmail()),
        );

        if (
            $googleId === '' ||
            ! hash_equals(
                (string) $user->google_id,
                $googleId,
            ) ||
            ! hash_equals(
                mb_strtolower(trim((string) $user->email)),
                $email,
            )
        ) {
            Log::channel('security')->notice(
                'Wrong Google account used for account deletion verification.',
                [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ],
            );

            return redirect()
                ->route('account.settings')
                ->withErrors([
                    'google' =>
                        'Hesabını silmek için Nozu hesabına bağlı Google hesabını seçmelisin.',
                ]);
        }

        $request->session()->put(
            'account_delete_google_verified_at',
            now()->timestamp,
        );

        $request->session()->regenerateToken();

        Log::channel('security')->info(
            'Google account deletion verification completed.',
            [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ],
        );

        return redirect()
            ->route('account.settings')
            ->with(
                'status',
                'Google hesabın doğrulandı. Hesap silme işlemini onaylayabilirsin.',
            );
    }

    private function uniqueUsername(
        string $email,
        string $name,
    ): string {
        $emailPrefix = Str::before(
            $email,
            '@',
        );

        $source = $emailPrefix !== ''
            ? $emailPrefix
            : $name;

        $base = Str::of($source)
            ->ascii()
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9_]+/',
                '_',
            )
            ->trim('_')
            ->substr(0, 24)
            ->value();

        if (mb_strlen($base) < 3) {
            $base = 'nozu_user';
        }

        $candidate = $base;
        $counter = 1;

        while (
            User::query()
                ->where('username', $candidate)
                ->exists()
        ) {
            $suffix = '_'.$counter;

            $candidate =
                mb_substr(
                    $base,
                    0,
                    30 - mb_strlen($suffix),
                ).$suffix;

            $counter++;
        }

        return $candidate;
    }
}
