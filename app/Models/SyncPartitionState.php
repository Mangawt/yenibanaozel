<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncPartitionState extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_WAITING_RATE_LIMIT = 'waiting_rate_limit';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_STOPPED = 'stopped';

    protected $fillable = [
        'sync_state_id',
        'year',
        'format',
        'status',
        'current_page',
        'last_successful_page',
        'last_page',
        'processed_count',
        'imported_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'last_error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function syncState(): BelongsTo
    {
        return $this->belongsTo(SyncState::class);
    }
}
