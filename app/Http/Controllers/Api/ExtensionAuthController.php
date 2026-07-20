<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExtensionLoginRequest;
use App\Http\Resources\Api\ExtensionUserResource;
use App\Models\User;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class ExtensionAuthController extends Controller
{
    public function login(ExtensionLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            Log::channel('security')->notice('Chrome extension login failed.', [
                'email_hash' => hash('sha256', mb_strtolower(trim($validated['email']))),
                'ip' => $request->ip(),
            ]);

            return ApiResponder::error('E-posta veya şifre hatalı.', [], 422);
        }

        $token = $user->createToken(
            $this->tokenName($validated['device_name']),
            ['extension:read', 'extension:list-write'],
            now()->addDays(30),
        );

        return $this->privateResponse(ApiResponder::success([
            'token' => $token->plainTextToken,
            'user' => (new ExtensionUserResource($user))->resolve($request),
        ]));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return $this->privateResponse(ApiResponder::success(['logged_out' => true]));
    }

    private function tokenName(string $deviceName): string
    {
        $safeName = trim(preg_replace('/[^\pL\pN\s._-]+/u', '', $deviceName) ?: '');

        return mb_substr($safeName !== '' ? $safeName : 'Nozu Chrome Extension', 0, 80);
    }

    private function privateResponse(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->remove('ETag');
        $response->headers->remove('Last-Modified');

        return $response;
    }
}
