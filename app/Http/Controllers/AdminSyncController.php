<?php

namespace App\Http\Controllers;

use App\Models\SyncState;
use App\Services\Settings;
use App\Services\SmartSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSyncController extends Controller
{
    public function index(Settings $settings)
    {
        return view('admin.sync-index', [
            'settings' => $settings->allPublic(),
            'states' => SyncState::query()->latest()->paginate(20),
            'stats' => [
                'running' => SyncState::query()->where('status', SyncState::STATUS_RUNNING)->count(),
                'waiting' => SyncState::query()->where('status', SyncState::STATUS_WAITING_RATE_LIMIT)->count(),
                'completed' => SyncState::query()->where('status', SyncState::STATUS_COMPLETED)->count(),
                'failed' => SyncState::query()->where('status', SyncState::STATUS_FAILED)->count(),
            ],
        ]);
    }

    public function start(Request $request, SmartSyncService $sync): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:anime,manga'],
            'mode' => ['required', 'in:missing,full,updates'],
            'sort' => ['required', 'in:POPULARITY_DESC,TRENDING_DESC,SCORE_DESC,START_DATE_DESC'],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'batch_size' => ['required', 'integer', 'min:1', 'max:5'],
            'page' => ['nullable', 'integer', 'min:1'],
            'max_page' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'genre' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1940', 'max:2100'],
            'season' => ['nullable', 'in:WINTER,SPRING,SUMMER,FALL'],
            'format' => ['nullable', 'string', 'max:40'],
        ]);

        $state = $sync->start($validated);

        return back()->with('status', "Sync baslatildi. ID: {$state->id}");
    }

    public function pause(SyncState $syncState, SmartSyncService $sync): RedirectResponse
    {
        $sync->pause($syncState);

        return back()->with('status', 'Sync duraklatildi.');
    }

    public function resume(SyncState $syncState, SmartSyncService $sync): RedirectResponse
    {
        $sync->resume($syncState);

        return back()->with('status', 'Sync devam ettirildi.');
    }

    public function stop(SyncState $syncState, SmartSyncService $sync): RedirectResponse
    {
        $sync->stop($syncState);

        return back()->with('status', 'Sync durduruldu.');
    }

    public function destroy(SyncState $syncState): RedirectResponse
    {
        abort_if(in_array($syncState->status, [SyncState::STATUS_RUNNING, SyncState::STATUS_WAITING_RATE_LIMIT], true), 422);

        $syncState->delete();

        return back()->with('status', 'Tarama durumu silindi.');
    }
}
