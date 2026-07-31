<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class WebAccountController extends Controller
{
    private const GOOGLE_VERIFICATION_LIFETIME = 600;

    public function edit(
        Request $request,
        Settings $settings,
    ): View {
        /** @var User $user */
        $user = $request->user();

        return view('account.settings', [
            'settings' => $settings->allPublic(),
            'user' => $user,

            'requiresGoogleVerification' =>
                $user->auth_provider === 'google',

            'googleDeletionVerified' =>
                $this->hasRecentGoogleVerification(
                    $request,
                ),

            'seo' => Seo::defaults([
                'title' => 'Hesap ayarları - nozu.me',
            ]),
        ]);
    }

    public function destroy(
        Request $request,
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (
            in_array(
                mb_strtolower((string) $user->role),
                [
                    'admin',
                    'super_admin',
                ],
                true,
            )
        ) {
            return back()->withErrors([
                'account' =>
                    'Yönetici hesapları site üzerinden silinemez.',
            ]);
        }

        $validated = $request->validate(
            [
                'confirmed' => [
                    'required',
                    'accepted',
                ],

                'password' => [
                    $user->auth_provider === 'google'
                        ? 'nullable'
                        : 'required',
                    'nullable',
                    'string',
                    'max:255',
                ],
            ],
            [
                'confirmed.required' =>
                    'Hesap silme işlemini onaylamalısın.',

                'confirmed.accepted' =>
                    'Hesap silme işlemini onaylamalısın.',

                'password.required' =>
                    'Hesabını silmek için mevcut şifreni yazmalısın.',
            ],
        );

        if ($user->auth_provider === 'google') {
            if (! $this->hasRecentGoogleVerification($request)) {
                return redirect()
                    ->route('account.settings')
                    ->withErrors([
                        'google' =>
                            'Hesabını silmeden önce Google hesabını yeniden doğrulamalısın.',
                    ]);
            }
        } elseif (
            ! Hash::check(
                (string) ($validated['password'] ?? ''),
                $user->password,
            )
        ) {
            Log::channel('security')->notice(
                'Nozu web account deletion failed because of invalid password.',
                [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ],
            );

            return back()->withErrors([
                'password' => 'Mevcut şifren hatalı.',
            ]);
        }

        $userId = (int) $user->id;
        $email = (string) $user->email;

        $avatarPath = $this->localMediaPath(
            $user->avatar_path,
        );

        $bannerPath = $this->localMediaPath(
            $user->banner_path,
        );

        try {
            DB::transaction(
                function () use (
                    $user,
                    $userId,
                    $email,
                ): void {
                    $user->tokens()->delete();

                    DB::table('sessions')
                        ->where('user_id', $userId)
                        ->delete();

                    DB::table('password_reset_tokens')
                        ->where('email', $email)
                        ->delete();

                    $user->delete();
                },
                attempts: 3,
            );
        } catch (Throwable $exception) {
            Log::channel('security')->error(
                'Nozu web account deletion failed.',
                [
                    'user_id' => $userId,
                    'ip' => $request->ip(),
                    'exception' => $exception->getMessage(),
                ],
            );

            return back()->withErrors([
                'account' =>
                    'Hesap silinirken bir hata oluştu. Lütfen tekrar dene.',
            ]);
        }

        Auth::logout();

        $request->session()->forget([
            'account_delete_google_verified_at',
            'google_account_deletion_user_id',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->deleteProfileMedia(
            $avatarPath,
            $userId,
            'avatar',
        );

        $this->deleteProfileMedia(
            $bannerPath,
            $userId,
            'banner',
        );

        Log::channel('security')->info(
            'Nozu web account permanently deleted.',
            [
                'deleted_user_id' => $userId,
                'email_hash' => hash(
                    'sha256',
                    mb_strtolower(trim($email)),
                ),
                'ip' => $request->ip(),
            ],
        );

        return redirect()
            ->route('home')
            ->with(
                'status',
                'Nozu hesabın kalıcı olarak silindi.',
            );
    }

    private function hasRecentGoogleVerification(
        Request $request,
    ): bool {
        $verifiedAt = (int) $request->session()->get(
            'account_delete_google_verified_at',
            0,
        );

        if ($verifiedAt <= 0) {
            return false;
        }

        $age = now()->timestamp - $verifiedAt;

        if (
            $age < 0 ||
            $age > self::GOOGLE_VERIFICATION_LIFETIME
        ) {
            $request->session()->forget(
                'account_delete_google_verified_at',
            );

            return false;
        }

        return true;
    }

    private function localMediaPath(
        mixed $value,
    ): ?string {
        $path = trim((string) $value);

        if ($path === '') {
            return null;
        }

        if (
            str_starts_with($path, 'http://') ||
            str_starts_with($path, 'https://')
        ) {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr(
                $path,
                strlen('storage/'),
            );
        }

        return $path !== ''
            ? $path
            : null;
    }

    private function deleteProfileMedia(
        ?string $path,
        int $deletedUserId,
        string $type,
    ): void {
        if ($path === null) {
            return;
        }

        try {
            Storage::disk('public')->delete($path);
        } catch (Throwable $exception) {
            Log::warning(
                'Deleted web user profile media could not be removed.',
                [
                    'deleted_user_id' => $deletedUserId,
                    'media_type' => $type,
                    'path' => $path,
                    'exception' => $exception->getMessage(),
                ],
            );
        }
    }
}
