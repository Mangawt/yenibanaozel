<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Media;
use App\Models\MediaList;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocialController extends Controller
{
    public function follow(User $user, Request $request): RedirectResponse
    {
        abort_if($request->user()->is($user), 422);

        $request->user()->following()->toggle($user->id);

        return back()->with('status', 'Takip durumu güncellendi.');
    }

    public function reportUser(User $user, Request $request): RedirectResponse
    {
        Report::query()->create([
            'user_id' => $request->user()->id,
            'reportable_type' => User::class,
            'reportable_id' => $user->id,
            'reason' => 'profile',
            'details' => $request->string('details')->limit(500)->value(),
        ]);

        return back()->with('status', 'Şikayet alındı.');
    }

    public function updateMediaList(Media $media, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:watching,reading,completed,paused,dropped,planned'],
        ]);

        MediaList::query()
            ->where('user_id', $request->user()->id)
            ->where('media_id', $media->id)
            ->where('status', '!=', 'favorite')
            ->delete();

        MediaList::query()->create([
            'user_id' => $request->user()->id,
            'media_id' => $media->id,
            'status' => $validated['status'],
        ]);

        return back()->with('status', 'Liste güncellendi.');
    }

    public function removeMediaList(Media $media, Request $request): RedirectResponse
    {
        MediaList::query()
            ->where('user_id', $request->user()->id)
            ->where('media_id', $media->id)
            ->where('status', '!=', 'favorite')
            ->delete();

        return back()->with('status', 'İzleme listenden kaldırıldı.');
    }

    public function toggleFavorite(Media $media, Request $request): RedirectResponse
    {
        $query = MediaList::query()
            ->where('user_id', $request->user()->id)
            ->where('media_id', $media->id)
            ->where('status', 'favorite');

        if ($query->exists()) {
            $query->delete();

            return back()->with('status', 'Favorilerden kaldırıldı.');
        }

        MediaList::query()->create([
            'user_id' => $request->user()->id,
            'media_id' => $media->id,
            'status' => 'favorite',
        ]);

        return back()->with('status', 'Favorilere eklendi.');
    }

    public function comment(Media $media, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
        ]);

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            abort_unless(Comment::query()->whereKey($parentId)->where('media_id', $media->id)->exists(), 404);
        }

        $media->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'body' => $validated['body'],
        ]);

        return back()->with('status', 'Yorum eklendi.');
    }

    public function voteComment(Comment $comment, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'integer', 'in:-1,1'],
        ]);

        DB::transaction(function () use ($comment, $request, $validated): void {
            CommentVote::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'comment_id' => $comment->id],
                ['value' => $validated['value']],
            );

            $comment->forceFill([
                'score' => CommentVote::query()->where('comment_id', $comment->id)->sum('value'),
            ])->save();
        });

        return back()->with('status', 'Oyun kaydedildi.');
    }

    public function reportComment(Comment $comment, Request $request): RedirectResponse
    {
        Report::query()->create([
            'user_id' => $request->user()->id,
            'reportable_type' => Comment::class,
            'reportable_id' => $comment->id,
            'reason' => 'comment',
            'details' => $request->string('details')->limit(500)->value(),
        ]);

        return back()->with('status', 'Yorum şikayeti alındı.');
    }
}
