<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportQueue extends Model
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_PROCESSING = 'processing';
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
    ];

    protected $casts = [
        'external_id' => 'integer',
        'attempts' => 'integer',
    ];
}
