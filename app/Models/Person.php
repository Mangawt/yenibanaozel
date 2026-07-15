<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'image',
        'credits_count',
    ];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_people')
            ->withPivot(['kind', 'role', 'language'])
            ->withTimestamps();
    }

    public function voicedCharacters(): HasMany
    {
        return $this->hasMany(MediaCharacter::class, 'voice_actor_id');
    }
}
