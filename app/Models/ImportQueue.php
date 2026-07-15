<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportQueue extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $table = 'import_queue';

    protected $fillable = [
        'source',
        'type',
        'external_id',
        'status',
        'attempts',
        'error_message',
        'batch_id',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'attempts' => 'integer',
    ];
}
