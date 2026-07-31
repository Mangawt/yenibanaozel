<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExtensionLoginRequest;
use App\Http\Requests\Api\ExtensionRegisterRequest;
use App\Http\Resources\Api\ExtensionUserResource;
use App\Models\User;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class ExtensionAuthController extends Controller
{
    /**
     * Mobil uygulama üzerinden yeni kullanıcı kaydı.
     */
    public function register(
        ExtensionRegisterRequest $request,
    ): JsonResponse {
        $validated = $request->validated();

        [$user, $token] = DB::transaction(
            function () use ($validated): array {
                $user = User::query()->create([
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'role' => 'user',
                    'theme' => 'system',
                ]);

                $token = $this->createAccessToken(
                    $user,
                    $validated['device_name'],
                );

                return [
                    $user,
                    $token,
                ];
            },
            attempts: 3,
        );

        $user->loadCount([
            'followers',
            'following',
        ]);

        Log::channel('security')->info(
            'Nozu API user registered.',
            [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'device_name' =>
                    $validated['device_name'],
            ],
        );

        return $this->privateResponse(
            ApiResponder::success([
                'token_type' => 'Bearer',
                'token' => $token->plainTextToken,
                'expires_at' => now()
                    ->addDays(30)
                    ->toAtomString(),
                'user' => (
                    new ExtensionUserResource($user)
                )->resolve($request),
            ]),
            201,
        );
    }

    /**
     * E-posta ve şifreyle giriş.
     */
    public function login(
        ExtensionLoginRequest $request,
    ): JsonResponse {
        $validated = $request->validated();

        $email = mb_strtolower(
            trim($validated['email']),
        );

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (
            ! $user ||
            ! Hash::check(
                $validated['password'],
                $user->password,
            )
        ) {
            Log::channel('security')->notice(
                'Nozu API login failed.',
                [
                    'email_hash' => hash(
                        'sha256',
                        $email,
                    ),
                    'ip' => $request->ip(),
                    'device_name' =>
                        $validated['device_name']
                            ?? null,
                ],
            );

            return ApiResponder::error(
                'E-posta veya şifre hatalı.',
                [],
                422,
            );
        }

        $token = $this->createAccessToken(
            $user,
            $validated['device_name'],
        );

        $user->loadCount([
            'followers',
            'following',
        ]);

        return $this->privateResponse(
            ApiResponder::success([
                'token_type' => 'Bearer',
                'token' => $token->plainTextToken,
                'expires_at' => now()
                    ->addDays(30)
                    ->toAtomString(),
                'user' => (
                    new ExtensionUserResource($user)
                )->resolve($request),
            ]),
        );
    }

    /**
     * Kullanılan mevcut token'ı iptal eder.
     */
    public function logout(
        Request $request,
    ): JsonResponse {
        $token = $request
            ->user()
            ?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return $this->privateResponse(
            ApiResponder::success([
                'logged_out' => true,
            ]),
        );
    }

    private function createAccessToken(
        User $user,
        string $deviceName,
    ): NewAccessToken {
        return $user->createToken(
            $this->tokenName($deviceName),
            $this->tokenAbilities(),
            now()->addDays(30),
        );
    }

    /**
     * Login, kayıt ve daha sonra Google girişinin
     * aynı yetkileri üretmesini sağlar.
     *
     * @return list<string>
     */
    private function tokenAbilities(): array
    {
        return [
            'extension:read',
            'extension:list-write',

            'app:read',
            'app:list-write',
            'app:comment-write',
            'app:vote-write',
            'app:report-write',
            'app:social-write',
        ];
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
        ?int $status = null,
    ): JsonResponse {
        if ($status !== null) {
            $response->setStatusCode($status);
        }

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
}
