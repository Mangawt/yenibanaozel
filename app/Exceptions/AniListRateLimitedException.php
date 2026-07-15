<?php

namespace App\Exceptions;

use RuntimeException;

class AniListRateLimitedException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter = 60)
    {
        parent::__construct('AniList rate limit yanıtı alındı.');
    }
}
