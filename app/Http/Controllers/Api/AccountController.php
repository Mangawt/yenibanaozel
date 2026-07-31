<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteAccountRequest;
use App\Models\User;
use App\Support\ApiResponder;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AccountController extends Controller
{
    public function destroy(
        DeleteAccountRequest $request,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->privateError(
                'Oturum bulunamadı.',
                [],
                401,
            );
        }

        if ($response = $this->adminDeletionError($user)) {
            return $response;
        }

        $validated = $request->validated();

        if (
            ! Hash::check(
                (string) $validated['password'],
                (string) $user->password,
            )
        ) {
            Log::channel('security')->notice(
                'Nozu account deletion failed because of invalid password.',
                [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ],
            );

            return $this->privateError(
                'Mevcut şifren hatalı.',
                [
                    'password' => [
                        'Mevcut şifren hatalı.',
                    ],
                ],
                422,
            );
        }

        return $this->permanentlyDeleteUser(
            $request,
            $user,
            'password',
        );
    }

    public function destroyWithGoogle(
        Request $request,
    ): JsonResponse {
        $validated = $request->validate(
            [
                'id_token' => [
                    'required',
                    'string',
                    'max:10000',
                ],

                'confirmed' => [
                    'required',
                    'accepted',
                ],
            ],
            [
                'id_token.required' =>
                    'Google doğrulama anahtarı gereklidir.',

                'confirmed.required' =>
                    'Hesap silme işlemini onaylamalısın.',

                'confirmed.accepted' =>
                    'Hesap silme işlemini onaylamalısın.',
            ],
        );

        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->privateError(
                'Oturum bulunamadı.',
                [],
                401,
            );
        }

        if ($response = $this->adminDeletionError($user)) {
            return $response;
        }

        if (! filled($user->google_id)) {
            return $this->privateError(
                'Bu hesaba bağlı bir Google hesabı bulunmuyor.',
                [
                    'google' => [
                        'Bu hesaba bağlı bir Google hesabı bulunmuyor.',
                    ],
                ],
                422,
            );
        }

        $expectedAudience = trim(
            (string) config(
                'services.google.client_id',
            ),
        );

        if ($expectedAudience === '') {
            Log::channel('security')->error(
                'Google account deletion is not configured.',
                [
                    'user_id' => $user->id,
                ],
            );

            return $this->privateError(
                'Google doğrulaması şu anda kullanılamıyor.',
                [],
                503,
            );
        }

        try {
            $googleClient = new GoogleClient([
                'client_id' => $expectedAudience,
            ]);

            $payload = $googleClient->verifyIdToken(
                (string) $validated['id_token'],
            );
        } catch (Throwable $exception) {
            Log::channel('security')->notice(
                'Google account deletion token verification failed.',
                [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                    'exception' => $exception->getMessage(),
                ],
            );

            return $this->privateError(
                'Google oturumu doğrulanamadı.',
                [],
                401,
            );
        }

        if (! is_array($payload)) {
            return $this->privateError(
                'Google oturumu geçersiz veya süresi dolmuş.',
                [],
                401,
            );
        }

        $googleId = trim(
            (string) ($payload['sub'] ?? ''),
        );

        $email = mb_strtolower(
            trim(
                (string) ($payload['email'] ?? ''),
            ),
        );

        $audience = trim(
            (string) ($payload['aud'] ?? ''),
        );

        $issuer = trim(
            (string) ($payload['iss'] ?? ''),
        );

        $emailVerified = filter_var(
            $payload['email_verified'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        $userEmail = mb_strtolower(
            trim((string) $user->email),
        );

        $userGoogleId = trim(
            (string) $user->google_id,
        );

        $claimsValid =
            $googleId !== '' &&
            $email !== '' &&
            $emailVerified &&
            hash_equals(
                $expectedAudience,
                $audience,
            ) &&
            hash_equals(
                $userGoogleId,
                $googleId,
            ) &&
            hash_equals(
                $userEmail,
                $email,
            ) &&
            in_array(
                $issuer,
                [
                    'accounts.google.com',
                    'https://accounts.google.com',
                ],
                true,
            );

        if (! $claimsValid) {
            Log::channel('security')->notice(
                'Google account deletion token claims rejected.',
                [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),

                    'google_id_matches' =>
                        $googleId !== '' &&
                        hash_equals(
                            $userGoogleId,
                            $googleId,
                        ),

                    'email_matches' =>
                        $email !== '' &&
                        hash_equals(
                            $userEmail,
                            $email,
                        ),

                    'audience_matches' =>
                        hash_equals(
                            $expectedAudience,
                            $audience,
                        ),

                    'email_verified' =>
                        $emailVerified,
                ],
            );

            return $this->privateError(
                'Hesabını silmek için Nozu hesabına bağlı Google hesabını seçmelisin.',
                [
                    'google' => [
                        'Bağlı Google hesabı doğrulanamadı.',
                    ],
                ],
                422,
            );
        }

        return $this->permanentlyDeleteUser(
            $request,
            $user,
            'google',
        );
    }

    private function permanentlyDeleteUser(
        Request $request,
        User $user,
        string $verificationMethod,
    ): JsonResponse {
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
                    /*
                     * Sanctum mobil oturumları.
                     */
                    $user->tokens()->delete();

                    /*
                     * Web oturumları.
                     */
                    DB::table('sessions')
                        ->where(
                            'user_id',
                            $userId,
                        )
                        ->delete();

                    /*
                     * Kullanılmamış parola sıfırlama kayıtları.
                     */
                    DB::table('password_reset_tokens')
                        ->where(
                            'email',
                            $email,
                        )
                        ->delete();

                    /*
                     * Yorumların user_id değeri NULL olur.
                     * Diğer ilişkiler foreign key kurallarına
                     * göre silinir veya anonimleştirilir.
                     */
                    $user->delete();
                },
                attempts: 3,
            );
        } catch (Throwable $exception) {
            Log::channel('security')->error(
                'Nozu account deletion failed.',
                [
                    'user_id' => $userId,
                    'ip' => $request->ip(),

                    'verification_method' =>
                        $verificationMethod,

                    'exception' =>
                        $exception->getMessage(),
                ],
            );

            return $this->privateError(
                'Hesap silinirken bir hata oluştu.',
                [],
                500,
            );
        }

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
            'Nozu account permanently deleted.',
            [
                'deleted_user_id' => $userId,

                'verification_method' =>
                    $verificationMethod,

                'email_hash' => hash(
                    'sha256',
                    mb_strtolower(
                        trim($email),
                    ),
                ),

                'ip' => $request->ip(),
            ],
        );

        return $this->privateSuccess([
            'account_deleted' => true,
        ]);
    }

    private function adminDeletionError(
        User $user,
    ): ?JsonResponse {
        if (
            ! in_array(
                mb_strtolower(
                    (string) $user->role,
                ),
                [
                    'admin',
                    'super_admin',
                ],
                true,
            )
        ) {
            return null;
        }

        return $this->privateError(
            'Yönetici hesapları uygulama üzerinden silinemez.',
            [],
            403,
        );
    }

    private function privateSuccess(
        array $data,
    ): JsonResponse {
        $response = ApiResponder::success(
            $data,
        );

        return $this->withPrivateHeaders(
            $response,
        );
    }

    private function privateError(
        string $message,
        array $errors,
        int $status,
    ): JsonResponse {
        $response = ApiResponder::error(
            $message,
            $errors,
            $status,
        );

        return $this->withPrivateHeaders(
            $response,
        );
    }

    private function withPrivateHeaders(
        JsonResponse $response,
    ): JsonResponse {
        $response->headers->set(
            'Cache-Control',
            'no-store, private',
        );

        $response->headers->remove('ETag');
        $response->headers->remove(
            'Last-Modified',
        );

        return $response;
    }

    private function localMediaPath(
        mixed $value,
    ): ?string {
        $path = trim(
            (string) $value,
        );

        if ($path === '') {
            return null;
        }

        if (
            str_starts_with(
                $path,
                'http://',
            ) ||
            str_starts_with(
                $path,
                'https://',
            )
        ) {
            return null;
        }

        $path = ltrim(
            $path,
            '/',
        );

        if (
            str_starts_with(
                $path,
                'storage/',
            )
        ) {
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
            Storage::disk('public')
                ->delete($path);
        } catch (Throwable $exception) {
            Log::warning(
                'Deleted user profile media could not be removed.',
                [
                    'deleted_user_id' =>
                        $deletedUserId,

                    'media_type' => $type,
                    'path' => $path,

                    'exception' =>
                        $exception->getMessage(),
                ],
            );
        }
    }
}
