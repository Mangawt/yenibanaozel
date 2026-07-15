<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiApplication extends Model
{
    protected $fillable = [
        'name',
        'email',
        'application_name',
        'website_url',
        'application_type',
        'expected_daily_requests',
        'commercial',
        'anime_database',
        'competitor_service',
        'will_attribute',
        'purpose',
        'description',
        'status',
        'assigned_plan',
        'admin_notes',
    ];

    protected $casts = [
        'commercial' => 'boolean',
        'anime_database' => 'boolean',
        'competitor_service' => 'boolean',
        'will_attribute' => 'boolean',
    ];
}
