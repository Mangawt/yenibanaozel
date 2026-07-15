<?php

namespace App\Http\Middleware;

use App\Support\ApiResponder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PublicApiRateLimit
{
    private const LIMIT = 60;

    public function handle(Request $request, Closure $next)
    {
        $key = 'public-api:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::LIMIT)) {
            $retryAfter = RateLimiter::availableIn($key);

            $response = ApiResponder::error('Çok fazla istek gönderildi. Lütfen biraz sonra tekrar dene.', [], 429);
            $response->headers->set('X-RateLimit-Limit', (string) self::LIMIT);
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('Retry-After', (string) $retryAfter);

            return $response;
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);
        $remaining = max(0, RateLimiter::remaining($key, self::LIMIT));

        $response->headers->set('X-RateLimit-Limit', (string) self::LIMIT);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }
}
