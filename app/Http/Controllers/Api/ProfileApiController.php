<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\MediaList;
use App\Models\Report;
use App\Models\User;
use App\Support\ApiResponder;
use App\Services\UserMediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ProfileApiController extends Controller
{
    public function show(
        Request $request,
        string $username,
    ): JsonResponse {
        $user = $this->findUser($username);

        $viewer = $request->user('sanctum');

        $mediaListQuery = MediaList::query()
            ->where('user_id', $user->id);

        $animeCount = (clone $mediaListQuery)
            ->whereHas(
                'media',
                fn (Builder $query) => $query->where('type', 'anime'),
            )
            ->where('status', '!=', 'favorite')
            ->count();

        $mangaCount = (clone $mediaListQuery)
            ->whereHas(
                'media',
                fn (Builder $query) => $query->where('type', 'manga'),
            )
            ->where('status', '!=', 'favorite')
            ->count();

        $completedCount = (clone $mediaListQuery)
            ->where('status', 'completed')
            ->count();

        $plannedCount = (clone $mediaListQuery)
            ->where('status', 'planned')
            ->count();

        $favoriteCount = (clone $mediaListQuery)
            ->where('status', 'favorite')
            ->count();

        $totalAnimeProgress = (clone $mediaListQuery)
            ->whereHas(
                'media',
                fn (Builder $query) => $query->where('type', 'anime'),
            )
            ->where('status', '!=', 'favorite')
            ->sum('progress');

        $totalMangaProgress = (clone $mediaListQuery)
            ->whereHas(
                'media',
                fn (Builder $query) => $query->where('type', 'manga'),
            )
            ->where('status', '!=', 'favorite')
            ->sum('progress');

        $averageScore = (clone $mediaListQuery)
            ->whereNotNull('score')
            ->where('status', '!=', 'favorite')
            ->avg('score');

        $commentsCount = Comment::query()
            ->where('user_id', $user->id)
            ->count();

        $commentScore = Comment::query()
            ->where('user_id', $user->id)
            ->sum('score');

        $favoriteItems = MediaList::query()
            ->where('user_id', $user->id)
            ->where('status', 'favorite')
            ->with('media')
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->map(
                fn (MediaList $entry): ?array =>
                    $entry->media
                        ? $this->mediaPayload($entry)
                        : null,
            )
            ->filter()
            ->values()
            ->all();

        $recentComments = Comment::query()
            ->where('user_id', $user->id)
            ->with([
                'media:id,type,slug,title,cover_image',
            ])
            ->latest()
            ->limit(8)
            ->get()
            ->map(
                fn (Comment $comment): array =>
                    $this->commentPayload($comment),
            )
            ->values()
            ->all();

        $statusDistribution = MediaList::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'favorite')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(
                fn ($value): int => (int) $value,
            )
            ->all();

        return ApiResponder::success([
            'profile' => $this->profilePayload(
                $user,
                $viewer,
            ),

            'stats' => [
                'anime_count' => $animeCount,
                'manga_count' => $mangaCount,
                'completed_count' => $completedCount,
                'planned_count' => $plannedCount,
                'favorite_count' => $favoriteCount,
                'comments_count' => $commentsCount,
                'comment_score' => (int) $commentScore,
                'total_anime_progress' => (int) $totalAnimeProgress,
                'total_manga_progress' => (int) $totalMangaProgress,
                'average_score' => $averageScore !== null
                    ? round((float) $averageScore, 1)
                    : null,
                'status_distribution' => $statusDistribution,
            ],

            'favorites' => $favoriteItems,
            'recent_comments' => $recentComments,
        ]);
    }

    public function comments(
        Request $request,
        string $username,
    ): JsonResponse {
        $user = $this->findUser($username);

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            50,
        );

        $comments = Comment::query()
            ->where('user_id', $user->id)
            ->with([
                'media:id,type,slug,title,cover_image',
            ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponder::paginated(
            $comments,
            $comments->getCollection()->map(
                fn (Comment $comment): array =>
                    $this->commentPayload($comment),
            ),
            $request,
        );
    }

    public function favorites(
        Request $request,
        string $username,
    ): JsonResponse {
        return $this->mediaListResponse(
            $request,
            $username,
            status: 'favorite',
        );
    }

    public function animeList(
        Request $request,
        string $username,
    ): JsonResponse {
        return $this->mediaListResponse(
            $request,
            $username,
            type: 'anime',
        );
    }

    public function mangaList(
        Request $request,
        string $username,
    ): JsonResponse {
        return $this->mediaListResponse(
            $request,
            $username,
            type: 'manga',
        );
    }

    public function update(
        Request $request,
    ): JsonResponse {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:80',
            ],
            'bio' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);


        $validated['name'] = trim(
            $validated['name'],
        );

        $validated['bio'] = isset($validated['bio'])
            ? trim($validated['bio'])
            : null;

        $user->fill($validated);
        $user->save();

        return ApiResponder::success([
            'profile' => $this->profilePayload(
                $user->fresh(),
                $user,
            ),
        ]);
    }

    public function uploadAvatar(
        Request $request,
    ): JsonResponse {
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
                'dimensions:min_width=128,min_height=128,max_width=4096,max_height=4096',
            ],
        ]);

        $user = $request->user();
        $storage = app(UserMediaStorage::class);

        $oldPath = $user->avatar_path;

        $newPath = $storage->uploadAvatar(
            $request->file('avatar'),
            (int) $user->id,
        );

        try {
            $user->avatar_path = $newPath;
            $user->save();
        } catch (Throwable $exception) {
            $storage->delete($newPath);

            throw $exception;
        }

        /*
         * Yeni görsel ve veritabanı kaydı başarılı olduktan
         * sonra eski dosya kaldırılır.
         */
        $storage->delete($oldPath);

        $freshUser = $user->fresh();

        return ApiResponder::success([
            'avatar' => $storage->url(
                $freshUser->avatar_path,
            ),
            'profile' => $this->profilePayload(
                $freshUser,
                $freshUser,
            ),
        ]);
    }

    public function deleteAvatar(
        Request $request,
    ): JsonResponse {
        $user = $request->user();
        $storage = app(UserMediaStorage::class);

        $oldPath = $user->avatar_path;

        $user->avatar_path = null;
        $user->save();

        $storage->delete($oldPath);

        $freshUser = $user->fresh();

        return ApiResponder::success([
            'avatar' => null,
            'profile' => $this->profilePayload(
                $freshUser,
                $freshUser,
            ),
        ]);
    }

    public function uploadBanner(
        Request $request,
    ): JsonResponse {
        $request->validate([
            'banner' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:6144',
                'dimensions:min_width=600,min_height=200,max_width=6000,max_height=3000',
            ],
        ]);

        $user = $request->user();
        $storage = app(UserMediaStorage::class);

        $oldPath = $user->banner_path;

        $newPath = $storage->uploadBanner(
            $request->file('banner'),
            (int) $user->id,
        );

        try {
            $user->banner_path = $newPath;
            $user->save();
        } catch (Throwable $exception) {
            $storage->delete($newPath);

            throw $exception;
        }

        /*
         * Yeni kapak başarıyla kaydedildikten sonra
         * önceki kapak kaldırılır.
         */
        $storage->delete($oldPath);

        $freshUser = $user->fresh();

        return ApiResponder::success([
            'banner' => $storage->url(
                $freshUser->banner_path,
            ),
            'profile' => $this->profilePayload(
                $freshUser,
                $freshUser,
            ),
        ]);
    }

    public function deleteBanner(
        Request $request,
    ): JsonResponse {
        $user = $request->user();
        $storage = app(UserMediaStorage::class);

        $oldPath = $user->banner_path;

        $user->banner_path = null;
        $user->save();

        $storage->delete($oldPath);

        $freshUser = $user->fresh();

        return ApiResponder::success([
            'banner' => null,
            'profile' => $this->profilePayload(
                $freshUser,
                $freshUser,
            ),
        ]);
    }

    public function follow(
        Request $request,
        string $username,
    ): JsonResponse {
        $viewer = $request->user();
        $target = $this->findUser($username);

        abort_if(
            $viewer->id === $target->id,
            422,
            'Kendini takip edemezsin.',
        );

        $result = $viewer->following()
            ->syncWithoutDetaching([
                $target->id,
            ]);

        if (! empty($result['attached'])) {
            AppNotification::query()->create([
                'user_id' => $target->id,
                'actor_id' => $viewer->id,
                'type' => 'follow',
                'title' => 'Yeni takipçi',
                'body' => $viewer->name.
                    ' seni takip etmeye başladı.',
                'target_type' => 'profile',
                'target_id' => $viewer->id,
                'target_slug' => $viewer->username,
            ]);
        }

        return ApiResponder::success([
            'is_following' => true,
            'followers_count' => $target
                ->followers()
                ->count(),
        ]);
    }

    public function unfollow(
        Request $request,
        string $username,
    ): JsonResponse {
        $viewer = $request->user();
        $target = $this->findUser($username);

        $viewer->following()
            ->detach($target->id);

        return ApiResponder::success([
            'is_following' => false,
            'followers_count' => $target
                ->followers()
                ->count(),
        ]);
    }

    public function followers(
        Request $request,
        string $username,
    ): JsonResponse {
        $user = $this->findUser($username);

        $perPage = min(
            max($request->integer('per_page', 24), 1),
            50,
        );

        $followers = $user
            ->followers()
            ->withCount([
                'followers',
                'following',
            ])
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponder::paginated(
            $followers,
            $followers
                ->getCollection()
                ->map(
                    fn (User $item): array =>
                        $this->connectionPayload($item),
                ),
            $request,
        );
    }

    public function following(
        Request $request,
        string $username,
    ): JsonResponse {
        $user = $this->findUser($username);

        $perPage = min(
            max($request->integer('per_page', 24), 1),
            50,
        );

        $following = $user
            ->following()
            ->withCount([
                'followers',
                'following',
            ])
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponder::paginated(
            $following,
            $following
                ->getCollection()
                ->map(
                    fn (User $item): array =>
                        $this->connectionPayload($item),
                ),
            $request,
        );
    }

    public function report(
        Request $request,
        string $username,
    ): JsonResponse {
        $viewer = $request->user();
        $target = $this->findUser($username);

        abort_if(
            $viewer->id === $target->id,
            422,
            'Kendi profilini raporlayamazsın.',
        );

        $validated = $request->validate([
            'reason' => [
                'required',
                Rule::in([
                    'spam',
                    'taklit',
                    'taciz',
                    'uygunsuz_icerik',
                    'nefret_soylemi',
                    'diger',
                ]),
            ],
            'details' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $alreadyReported = Report::query()
            ->where('user_id', $viewer->id)
            ->where(
                'reportable_type',
                User::class,
            )
            ->where(
                'reportable_id',
                $target->id,
            )
            ->exists();

        if ($alreadyReported) {
            return ApiResponder::error(
                'Bu profili daha önce raporladınız.',
                [],
                409,
            );
        }

        Report::query()->create([
            'user_id' => $viewer->id,
            'reportable_type' => User::class,
            'reportable_id' => $target->id,
            'reason' => $validated['reason'],
            'details' => isset($validated['details'])
                ? trim($validated['details'])
                : null,
            'status' => 'open',
        ]);

        return ApiResponder::success([
            'reported' => true,
        ]);
    }

    private function connectionPayload(
        User $user,
    ): array {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => $this->storageUrl(
                $user->avatar_path,
            ),
            'bio' => $user->bio,
            'role' => $user->role,
            'is_admin' => in_array($user->role, ['admin', 'super_admin'], true),
            'followers_count' => (int) (
                $user->followers_count ?? 0
            ),
            'following_count' => (int) (
                $user->following_count ?? 0
            ),
        ];
    }

    private function mediaListResponse(
        Request $request,
        string $username,
        ?string $type = null,
        ?string $status = null,
    ): JsonResponse {
        $user = $this->findUser($username);

        $perPage = min(
            max($request->integer('per_page', 24), 1),
            50,
        );

        $query = MediaList::query()
            ->where('user_id', $user->id)
            ->with('media');

        if ($type !== null) {
            $query->whereHas(
                'media',
                fn (Builder $mediaQuery) =>
                    $mediaQuery->where('type', $type),
            );
        }

        if ($status !== null) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', 'favorite');
        }

        $entries = $query
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponder::paginated(
            $entries,
            $entries->getCollection()
                ->map(
                    fn (MediaList $entry): ?array =>
                        $entry->media
                            ? $this->mediaPayload($entry)
                            : null,
                )
                ->filter()
                ->values(),
            $request,
        );
    }

    private function findUser(
        string $username,
    ): User {
        return User::query()
            ->whereRaw(
                'LOWER(username) = ?',
                [mb_strtolower(trim($username))],
            )
            ->withCount([
                'followers',
                'following',
            ])
            ->firstOrFail();
    }

    private function profilePayload(
        User $user,
        ?User $viewer,
    ): array {
        $isOwnProfile = $viewer?->id === $user->id;

        $isFollowing = false;

        if (
            $viewer !== null &&
            ! $isOwnProfile
        ) {
            $isFollowing = $viewer
                ->following()
                ->whereKey($user->id)
                ->exists();
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => $this->storageUrl(
                $user->avatar_path,
            ),
            'banner' => $this->storageUrl(
                $user->banner_path,
            ),
            'bio' => $user->bio,
            'followers_count' => (int) (
                $user->followers_count
                ?? $user->followers()->count()
            ),
            'following_count' => (int) (
                $user->following_count
                ?? $user->following()->count()
            ),
            'is_own_profile' => $isOwnProfile,
            'is_following' => $isFollowing,
            'joined_at' => $user->created_at?->toAtomString(),
            'url' => route(
                'profile.show',
                $user->username,
            ),
        ];
    }

    private function mediaPayload(
        MediaList $entry,
    ): array {
        $media = $entry->media;

        return [
            'id' => $media->id,
            'type' => $media->type,
            'slug' => $media->slug,
            'title' => $media->title,
            'cover_image' => $media->cover_image,
            'banner_image' => $media->banner_image,
            'format' => $media->format,
            'status' => $media->status,
            'average_score' => $media->average_score,
            'popularity' => $media->popularity,
            'genres' => $media->genres,
            'year' => $media->season_year
                ?? $media->start_year,
            'list_status' => $entry->status,
            'progress' => (int) $entry->progress,
            'user_score' => $entry->score,
            'updated_at' => $entry->updated_at?->toAtomString(),
        ];
    }

    private function commentPayload(
        Comment $comment,
    ): array {
        return [
            'id' => $comment->id,
            'media_id' => $comment->media_id,
            'parent_id' => $comment->parent_id,
            'body' => $comment->body,
            'is_spoiler' => (bool) $comment->is_spoiler,
            'score' => (int) $comment->score,
            'created_at' => $comment->created_at?->toAtomString(),
            'updated_at' => $comment->updated_at?->toAtomString(),

            'media' => $comment->media
                ? [
                    'id' => $comment->media->id,
                    'type' => $comment->media->type,
                    'slug' => $comment->media->slug,
                    'title' => $comment->media->title,
                    'cover_image' => $comment->media->cover_image,
                ]
                : null,
        ];
    }

    private function storageUrl(
        ?string $path,
    ): ?string {
        return app(
            UserMediaStorage::class,
        )->url($path);
    }

    private function deletePublicFile(
        ?string $path,
    ): void {
        app(
            UserMediaStorage::class,
        )->delete($path);
    }
}
