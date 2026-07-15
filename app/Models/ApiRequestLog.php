<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    protected $fillable = [
        'api_client_id',
        'api_key_id',
        'endpoint',
        'method',
        'status_code',
        'ip_address',
        'origin',
        'referer',
        'user_agent',
        'response_time_ms',
        'response_size',
        'request_cost',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function key(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }
}
