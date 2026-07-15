<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'api_client_id',
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'status',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
