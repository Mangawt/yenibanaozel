<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ExtensionUserResource;
use App\Models\User;
use App\Support\ApiResponder;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleMobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => [
                'required',
                'string',
                'max:10000',
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $expectedAudience = trim(
            (string) config('services.google.client_id'),
        );

        if ($expectedAudience === '') {
            Log::channel('security')->error(
                'Google mobile login is not configured.',
            );

            return $this->privateResponse(
                ApiResponder::error(
                    'Google ile giriş şu anda kullanılamıyor.',
                    [],
                    503,
                ),
            );
        }

        try {
            $googleClient = new GoogleClient([
                'client_id' => $expectedAudience,
            ]);

            $payload = $googleClient->verifyIdToken(
                $validated['id_token'],
            );
        } catch (Throwable $exception) {
            Log::channel('security')->notice(
                'Google mobile ID token verification failed.',
                [
                    'ip' => $request->ip(),
                    'exception' => $exception->getMessage(),
                ],
            );

            return $this->privateResponse(
                ApiResponder::error(
                    'Google oturumu doğrulanamadı.',
                    [],
                    401,
                ),
            );
        }

        if (! is_array($payload)) {
            return $this->privateResponse(
                ApiResponder::error(
                    'Google oturumu geçersiz veya süresi dolmuş.',
                    [],
                    401,
                ),
            );
        }

        $googleId = trim(
            (string) ($payload['sub'] ?? ''),
        );

        $email = mb_strtolower(
            trim((string) ($payload['email'] ?? '')),
        );

        $name = trim(
            (string) ($payload['name'] ?? ''),
        );

        $avatar = trim(
            (string) ($payload['picture'] ?? ''),
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

        if (
            $googleId === '' ||
            $email === '' ||
            ! $emailVerified ||
            ! hash_equals($expectedAudience, $audience) ||
            ! in_array(
                $issuer,
                [
                    'accounts.google.com',
                    'https://accounts.google.com',
                ],
                true,
            )
        ) {
            Log::channel('security')->notice(
                'Google mobile token claims rejected.',
                [
                    'ip' => $request->ip(),
                    'has_google_id' => $googleId !== '',
                    'has_email' => $email !== '',
                    'email_verified' => $emailVerified,
                    'audience_matches' => hash_equals(
                        $expectedAudience,
                        $audience,
                    ),
                ],
            );

            return $this->privateResponse(
                ApiResponder::error(
                    'Google hesabı doğrulanamadı.',
                    [],
                    401,
                ),
            );
        }

        try {
            $user = DB::transaction(
                function () use (
                    $googleId,
                    $email,
                    $name,
                    $avatar,
                ): User {
                    $user = User::query()
                        ->where('google_id', $googleId)
                        ->lockForUpdate()
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
                            ! hash_equals(
                                (string) $user->google_id,
                                $googleId,
                            )
                        ) {
                            throw new \RuntimeException(
                                'Bu e-posta başka bir Google hesabına bağlı.',
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

                    return User::query()->create([
                        'name' =>
                            $name !== ''
                                ? mb_substr($name, 0, 80)
                                : 'Nozu Kullanıcısı',

                        'username' => $this->uniqueUsername(
                            $email,
                            $name,
                        ),

                        'email' => $email,

                        'password' => Hash::make(
                            Str::random(64),
                        ),

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
                'Google mobile user login failed.',
                [
                    'email_hash' => hash(
                        'sha256',
                        $email,
                    ),
                    'ip' => $request->ip(),
                    'exception' => $exception->getMessage(),
                ],
            );

            return $this->privateResponse(
                ApiResponder::error(
                    'Google hesabıyla giriş tamamlanamadı.',
                    [],
                    422,
                ),
            );
        }

        $expiresAt = now()->addDays(30);

        $token = $user->createToken(
            $this->tokenName(
                (string) ($validated['device_name']
                    ?? 'Nozu Android Uygulaması'),
            ),
            [
                'extension:read',
                'extension:list-write',

                'app:read',
                'app:list-write',
                'app:comment-write',
                'app:vote-write',
                'app:report-write',
                'app:social-write',
            ],
            $expiresAt,
        );

        Log::channel('security')->info(
            'Google mobile login completed.',
            [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ],
        );

        return $this->privateResponse(
            ApiResponder::success([
                'token_type' => 'Bearer',
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toAtomString(),

                'user' => (new ExtensionUserResource($user))
                    ->resolve($request),
            ]),
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

    private function tokenName(
        string $deviceName,
    ): string {
        $safeName = trim(
            preg_replace(
                '/[^\pL\pN\s._-]+/u',
                '',
                $deviceName,
            ) ?: '',
        );

        return mb_substr(
            $safeName !== ''
                ? $safeName
                : 'Nozu Android',
            0,
            80,
        );
    }

    private function privateResponse(
        JsonResponse $response,
    ): JsonResponse {
        $response->headers->set(
            'Cache-Control',
            'no-store, private',
        );

        $response->headers->remove('ETag');
        $response->headers->remove('Last-Modified');

        return $response;
    }
}
