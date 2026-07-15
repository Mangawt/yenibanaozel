<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Character extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'image',
        'media_count',
    ];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_characters')
            ->withPivot(['role', 'voice_actor_id', 'language'])
            ->withTimestamps();
    }
}
