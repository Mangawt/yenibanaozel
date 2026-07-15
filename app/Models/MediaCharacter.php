<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaCharacter extends Model
{
    protected $fillable = [
        'media_id',
        'character_id',
        'voice_actor_id',
        'role',
        'language',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function voiceActor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'voice_actor_id');
    }
}
