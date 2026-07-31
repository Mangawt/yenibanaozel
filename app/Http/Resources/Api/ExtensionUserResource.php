<?php

namespace App\Http\Resources\Api;

use App\Services\UserMediaStorage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtensionUserResource extends JsonResource
{
    public function toArray(
        Request $request,
    ): array {
        $role = (string) (
            $this->role ?? 'user'
        );

        $authProvider = mb_strtolower(
            trim(
                (string) (
                    $this->auth_provider
                    ?? 'password'
                ),
            ),
        );

        $hasPassword = in_array(
            $authProvider,
            [
                'password',
                'password_google',
            ],
            true,
        );

        $usesGoogle = in_array(
            $authProvider,
            [
                'google',
                'password_google',
            ],
            true,
        );

        $mediaStorage = app(
            UserMediaStorage::class,
        );

        $avatar = null;

        if (
            filled($this->avatar_path)
        ) {
            $avatar = $mediaStorage->url(
                $this->avatar_path,
            );
        } elseif (
            filled($this->google_avatar)
        ) {
            $avatar = (string) $this->google_avatar;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,

            'auth_provider' => $authProvider,
            'has_password' => $hasPassword,
            'uses_google' => $usesGoogle,

            'role' => $role,
            'is_admin' => in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true,
            ),

            'avatar' => $avatar,

            'banner' => $mediaStorage->url(
                $this->banner_path,
            ),

            'bio' => $this->bio,

            'followers_count' => (int) (
                $this->followers_count ?? 0
            ),

            'following_count' => (int) (
                $this->following_count ?? 0
            ),

            'created_at' => $this
                ->created_at
                ?->toAtomString(),
        ];
    }
}
