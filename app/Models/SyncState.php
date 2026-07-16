<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncState extends Model
{
    public const STATUS_IDLE = 'idle';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_WAITING_RATE_LIMIT = 'waiting_rate_limit';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_STOPPED = 'stopped';

    protected $fillable = [
        'source',
        'type',
        'mode',
        'filters',
        'status',
        'current_page',
        'last_successful_page',
        'last_external_id',
        'requests_in_window',
        'window_started_at',
        'processed_count',
        'existing_count',
        'imported_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'last_error',
        'started_at',
        'paused_at',
        'finished_at',
        'last_scan_at',
        'next_run_at',
        'lock_owner',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_scan_at' => 'datetime',
        'next_run_at' => 'datetime',
        'window_started_at' => 'datetime',
    ];

    public function partitions(): HasMany
    {
        return $this->hasMany(SyncPartitionState::class);
    }
}
