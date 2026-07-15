<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Studio extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'image',
        'media_count',
    ];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_studios')
            ->withPivot(['role'])
            ->withTimestamps();
    }
}
