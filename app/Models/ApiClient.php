<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiClient extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REJECTED = 'rejected';

    public const PLAN_INTERNAL = 'internal';
    public const PLAN_FREE = 'free';
    public const PLAN_DEVELOPER = 'developer';
    public const PLAN_COMMERCIAL = 'commercial';

    protected $fillable = [
        'user_id',
        'name',
        'contact_email',
        'website_url',
        'application_type',
        'description',
        'status',
        'plan',
        'allowed_domains',
        'allowed_ips',
        'custom_limits',
        'permissions',
        'attribution_required',
        'commercial_use_allowed',
        'competitor_use_allowed',
        'auto_suspend_enabled',
        'abuse_score',
        'suspended_until',
        'terms_accepted_at',
        'terms_version',
        'terms_ip',
        'license_notes',
    ];

    protected $casts = [
        'allowed_domains' => 'array',
        'allowed_ips' => 'array',
        'custom_limits' => 'array',
        'permissions' => 'array',
        'attribution_required' => 'boolean',
        'commercial_use_allowed' => 'boolean',
        'competitor_use_allowed' => 'boolean',
        'auto_suspend_enabled' => 'boolean',
        'suspended_until' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    public function keys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }
}
