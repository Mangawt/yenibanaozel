<?php

namespace App\Http\Controllers\Api;

use App\Services\UserMediaStorage;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 20), 1),
            50,
        );

        $notifications = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'actor:id,name,username,avatar_path,role',
            ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponder::paginated(
            $notifications,
            $notifications
                ->getCollection()
                ->map(
                    fn (AppNotification $notification): array =>
                        $this->payload($notification),
                ),
            $request,
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponder::success([
            'unread_count' => $count,
        ]);
    }

    public function read(
        AppNotification $notification,
        Request $request,
    ): JsonResponse {
        abort_unless(
            $notification->user_id === $request->user()->id,
            404,
        );

        if ($notification->read_at === null) {
            $notification->forceFill([
                'read_at' => now(),
            ])->save();
        }

        $notification->loadMissing([
            'actor:id,name,username,avatar_path,role',
        ]);

        return ApiResponder::success([
            'notification' => $this->payload(
                $notification,
            ),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $updated = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return ApiResponder::success([
            'updated' => $updated,
        ]);
    }

    private function payload(
        AppNotification $notification,
    ): array {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'target_type' => $notification->target_type,
            'target_id' => $notification->target_id,
            'target_slug' => $notification->target_slug,
            'data' => $notification->data ?? [],
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification
                ->read_at
                ?->toAtomString(),
            'created_at' => $notification
                ->created_at
                ?->toAtomString(),

            'actor' => $notification->actor
                ? [
                    'id' => $notification->actor->id,
                    'name' => $notification->actor->name,
                    'username' => $notification->actor->username,
                    'avatar' => app(
                        UserMediaStorage::class,
                    )->url(
                        $notification->actor->avatar_path,
                    ),
                    'role' => $notification->actor->role,
                    'is_admin' =>
                        in_array($notification->actor->role, ['admin', 'super_admin'], true),
                ]
                : null,
        ];
    }
}
