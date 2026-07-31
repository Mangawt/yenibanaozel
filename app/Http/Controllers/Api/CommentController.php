<?php

namespace App\Http\Controllers\Api;

use App\Services\UserMediaStorage;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Media;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    /**
     * Bir serinin ana yorumlarını ve doğrudan yanıtlarını listeler.
     */
    public function index(Media $media, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'score'])],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $sort = $validated['sort'] ?? 'newest';

        $query = Comment::query()
            ->where('media_id', $media->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,username,avatar_path,role',
                'replies' => function ($query): void {
                    $query
                        ->with('user:id,name,username,avatar_path,role')
                        ->oldest();
                },
            ]);

        match ($sort) {
            'oldest' => $query->oldest(),
            'score' => $query
                ->orderByDesc('score')
                ->latest('id'),
            default => $query->latest(),
        };

        $comments = $query->paginate($perPage);

        $allComments = collect($comments->items())
            ->flatMap(function (Comment $comment): array {
                return [
                    $comment,
                    ...$comment->replies->all(),
                ];
            });

        $viewer = Auth::guard('sanctum')->user();

        $viewerVotes = collect();
        $reportedCommentIds = collect();

        if ($viewer && $allComments->isNotEmpty()) {
            $commentIds = $allComments
                ->pluck('id')
                ->filter()
                ->values();

            $viewerVotes = CommentVote::query()
                ->where('user_id', $viewer->id)
                ->whereIn('comment_id', $commentIds)
                ->pluck('value', 'comment_id');

            $reportedCommentIds = Report::query()
                ->where('user_id', $viewer->id)
                ->where('reportable_type', Comment::class)
                ->whereIn('reportable_id', $commentIds)
                ->pluck('reportable_id');
        }

        return response()->json([
            'success' => true,
            'data' => collect($comments->items())
                ->map(fn (Comment $comment): array => $this->transformComment(
                    $comment,
                    $viewerVotes,
                    $reportedCommentIds,
                    true,
                ))
                ->values(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
                'last_page' => $comments->lastPage(),
                'sort' => $sort,
            ],
            'links' => [
                'next' => $comments->nextPageUrl(),
                'previous' => $comments->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Yeni yorum veya ana yoruma yanıt oluşturur.
     */
    public function store(Media $media, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => [
                'required',
                'string',
                'min:2',
                'max:2000',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:comments,id',
            ],
            'is_spoiler' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $parent = null;

        if (! empty($validated['parent_id'])) {
            $parent = Comment::query()
                ->whereKey($validated['parent_id'])
                ->where('media_id', $media->id)
                ->firstOrFail();

            if ($parent->parent_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Yalnızca ana yorumlara yanıt verilebilir.',
                ], 422);
            }
        }

        $comment = Comment::query()->create([
            'user_id' => $request->user()->id,
            'media_id' => $media->id,
            'parent_id' => $parent?->id,
            'body' => trim($validated['body']),
            'is_spoiler' => (bool) ($validated['is_spoiler'] ?? false),
            'score' => 0,
        ]);

        $comment->load('user:id,name,username,avatar_path,role');

        $this->createCommentNotifications(
            $comment,
            $request->user(),
            $parent,
            $media,
        );

        return response()->json([
            'success' => true,
            'message' => $parent
                ? 'Yanıt eklendi.'
                : 'Yorum eklendi.',
            'data' => $this->transformComment(
                $comment,
                collect(),
                collect(),
                false,
            ),
        ], 201);
    }

    /**
     * Kullanıcının kendi yorumunu düzenler.
     */
    public function update(
        Comment $comment,
        Request $request,
    ): JsonResponse {
        if ($comment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Yalnızca kendi yorumunuzu düzenleyebilirsiniz.',
            ], 403);
        }

        $validated = $request->validate([
            'body' => [
                'required',
                'string',
                'min:2',
                'max:2000',
            ],
            'is_spoiler' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $comment->forceFill([
            'body' => trim($validated['body']),
            'is_spoiler' => (bool) (
                $validated['is_spoiler']
                ?? $comment->is_spoiler
            ),
        ])->save();

        $comment->load(
            'user:id,name,username,avatar_path,role',
        );

        return response()->json([
            'success' => true,
            'message' => 'Yorum güncellendi.',
            'data' => $this->transformComment(
                $comment,
                collect(),
                collect(),
                false,
            ),
        ]);
    }

    /**
     * Yorumu sahibi veya yetkili personel tarafından siler.
     */
    public function destroy(
        Comment $comment,
        Request $request,
    ): JsonResponse {
        $user = $request->user();

        $isOwner = $comment->user_id === $user->id;

        $isModerator = in_array(
            $user->role,
            [
                'moderator',
                'admin',
                'super_admin',
            ],
            true,
        );

        if (! $isOwner && ! $isModerator) {
            return response()->json([
                'success' => false,
                'message' => 'Bu yorumu silme yetkiniz bulunmuyor.',
            ], 403);
        }

        $deletedCommentIds = Comment::query()
            ->whereKey($comment->id)
            ->orWhere('parent_id', $comment->id)
            ->pluck('id');

        DB::transaction(function () use (
            $comment,
            $deletedCommentIds,
        ): void {
            AppNotification::query()
                ->where('target_type', 'comment')
                ->whereIn('target_id', $deletedCommentIds)
                ->delete();

            $comment->delete();
        });

        return response()->json([
            'success' => true,
            'message' => $comment->parent_id === null
                ? 'Yorum ve yanıtları silindi.'
                : 'Yanıt silindi.',
            'data' => [
                'comment_id' => $comment->id,
                'deleted_comment_ids' => $deletedCommentIds->values(),
            ],
        ]);
    }

    /**
     * Oy ekler, değiştirir veya aynı oya tekrar basılırsa oyu kaldırır.
     */
    public function vote(Comment $comment, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'value' => [
                'required',
                'integer',
                Rule::in([-1, 1]),
            ],
        ]);

        $result = DB::transaction(function () use (
            $comment,
            $request,
            $validated,
        ): array {
            $lockedComment = Comment::query()
                ->whereKey($comment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingVote = CommentVote::query()
                ->where('user_id', $request->user()->id)
                ->where('comment_id', $lockedComment->id)
                ->lockForUpdate()
                ->first();

            $userVote = (int) $validated['value'];
            $action = 'created';

            if ($existingVote) {
                if ((int) $existingVote->value === $userVote) {
                    $existingVote->delete();
                    $userVote = 0;
                    $action = 'removed';
                } else {
                    $existingVote->update([
                        'value' => $userVote,
                    ]);

                    $action = 'updated';
                }
            } else {
                CommentVote::query()->create([
                    'user_id' => $request->user()->id,
                    'comment_id' => $lockedComment->id,
                    'value' => $userVote,
                ]);
            }

            $score = (int) CommentVote::query()
                ->where('comment_id', $lockedComment->id)
                ->sum('value');

            $lockedComment->forceFill([
                'score' => $score,
            ])->save();

            return [
                'score' => $score,
                'user_vote' => $userVote,
                'action' => $action,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => match ($result['action']) {
                'removed' => 'Oyun kaldırıldı.',
                'updated' => 'Oyun değiştirildi.',
                default => 'Oyun kaydedildi.',
            },
            'data' => [
                'comment_id' => $comment->id,
                'score' => $result['score'],
                'user_vote' => $result['user_vote'],
            ],
        ]);
    }

    /**
     * Yorumu admin incelemesine gönderir.
     */
    public function report(Comment $comment, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => [
                'required',
                Rule::in([
                    'spam',
                    'hakaret',
                    'spoiler',
                    'nefret_soylemi',
                    'uygunsuz_icerik',
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
            ->where('user_id', $request->user()->id)
            ->where('reportable_type', Comment::class)
            ->where('reportable_id', $comment->id)
            ->exists();

        if ($alreadyReported) {
            return response()->json([
                'success' => false,
                'message' => 'Bu yorumu daha önce raporladınız.',
            ], 409);
        }

        Report::query()->create([
            'user_id' => $request->user()->id,
            'reportable_type' => Comment::class,
            'reportable_id' => $comment->id,
            'reason' => $validated['reason'],
            'details' => isset($validated['details'])
                ? trim($validated['details'])
                : null,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Yorum raporlandı ve admin incelemesine gönderildi.',
        ], 201);
    }

    /**
     * @param Collection<int|string, int> $viewerVotes
     * @param Collection<int, int> $reportedCommentIds
     * @return array<string, mixed>
     */
    private function transformComment(
        Comment $comment,
        Collection $viewerVotes,
        Collection $reportedCommentIds,
        bool $includeReplies,
    ): array {
        $data = [
            'id' => $comment->id,
            'media_id' => $comment->media_id,
            'parent_id' => $comment->parent_id,
            'body' => $comment->body,
            'is_spoiler' => (bool) $comment->is_spoiler,
            'score' => (int) $comment->score,
            'user_vote' => (int) ($viewerVotes[$comment->id] ?? 0),
            'reported_by_me' => $reportedCommentIds->contains(
                $comment->id,
            ),
            'user' => [
                'id' => $comment->user?->id,
                'name' => $comment->user?->name,
                'username' => $comment->user?->username,
                'avatar' => $this->avatarUrl(
                    $comment->user?->avatar_path,
                ),
                'role' => $comment->user?->role,
                'is_admin' =>
                    in_array($comment->user?->role, ['admin', 'super_admin'], true),
            ],
            'created_at' => $comment->created_at?->toAtomString(),
            'updated_at' => $comment->updated_at?->toAtomString(),
        ];

        if ($includeReplies) {
            $data['replies'] = $comment->replies
                ->map(fn (Comment $reply): array => $this->transformComment(
                    $reply,
                    $viewerVotes,
                    $reportedCommentIds,
                    false,
                ))
                ->values();
        }

        return $data;
    }


    private function createCommentNotifications(
        Comment $comment,
        User $actor,
        ?Comment $parent,
        Media $media,
    ): void {
        $notifiedUserIds = [];

        if (
            $parent !== null
            && $parent->user_id !== $actor->id
        ) {
            AppNotification::query()->create([
                'user_id' => $parent->user_id,
                'actor_id' => $actor->id,
                'type' => 'reply',
                'title' => 'Yorumuna yanıt geldi',
                'body' => $actor->name.
                    ' yorumuna yanıt verdi.',
                'target_type' => 'comment',
                'target_id' => $comment->id,
                'target_slug' => $media->slug,
                'data' => [
                    'media_id' => $media->id,
                    'media_title' => $media->title,
                    'media_type' => $media->type,
                    'comment_id' => $comment->id,
                    'parent_id' => $parent->id,
                ],
            ]);

            $notifiedUserIds[] = $parent->user_id;
        }

        preg_match_all(
            '/(?<![\pL\pN_])@([a-zA-Z0-9_.]{3,30})/u',
            $comment->body,
            $matches,
        );

        $usernames = collect(
            $matches[1] ?? [],
        )
            ->map(
                fn (string $username): string =>
                    mb_strtolower($username),
            )
            ->unique()
            ->take(20)
            ->values();

        if ($usernames->isEmpty()) {
            return;
        }

        $mentionedUsers = User::query()
            ->whereIn(
                DB::raw('LOWER(username)'),
                $usernames->all(),
            )
            ->get([
                'id',
                'username',
            ]);

        foreach ($mentionedUsers as $mentionedUser) {
            if (
                $mentionedUser->id === $actor->id
                || in_array(
                    $mentionedUser->id,
                    $notifiedUserIds,
                    true,
                )
            ) {
                continue;
            }

            AppNotification::query()->create([
                'user_id' => $mentionedUser->id,
                'actor_id' => $actor->id,
                'type' => 'mention',
                'title' => 'Senden bahsetti',
                'body' => $actor->name.
                    ' bir yorumda senden bahsetti.',
                'target_type' => 'comment',
                'target_id' => $comment->id,
                'target_slug' => $media->slug,
                'data' => [
                    'media_id' => $media->id,
                    'media_title' => $media->title,
                    'media_type' => $media->type,
                    'comment_id' => $comment->id,
                ],
            ]);
        }
    }

    private function avatarUrl(?string $path): ?string
    {
        return app(UserMediaStorage::class)->url($path);
    }
}
