<?php

namespace App\Http\Middleware;

use App\Support\ApiResponder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ExtensionRateLimit
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $limits = match ($type) {
            'login' => $this->loginLimits($request),
            'read' => [
                ['key' => 'extension:read:user:'.$request->user()?->id, 'max' => 120, 'decay' => 60],
                ['key' => 'extension:read:ip:'.$request->ip(), 'max' => 180, 'decay' => 60],
            ],
            'write' => [
                ['key' => 'extension:write:user:'.$request->user()?->id, 'max' => 30, 'decay' => 60],
            ],
            'delete' => [
                ['key' => 'extension:delete:user:'.$request->user()?->id, 'max' => 10, 'decay' => 60],
            ],
            default => [],
        };

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                $retryAfter = RateLimiter::availableIn($limit['key']);
                $response = ApiResponder::error('Çok fazla istek gönderildi. Lütfen biraz sonra tekrar dene.', [], 429);
                $response->headers->set('Retry-After', (string) $retryAfter);
                $response->headers->set('X-RateLimit-Limit', (string) $limit['max']);
                $response->headers->set('X-RateLimit-Remaining', '0');

                return $response;
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], $limit['decay']);
        }

        $response = $next($request);

        if ($limits !== []) {
            $primary = $limits[0];
            $response->headers->set('X-RateLimit-Limit', (string) $primary['max']);
            $response->headers->set('X-RateLimit-Remaining', (string) max(0, RateLimiter::remaining($primary['key'], $primary['max'])));
        }

        return $response;
    }

    private function loginLimits(Request $request): array
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $emailHash = hash('sha256', $email);

        return [
            ['key' => 'extension:login:ip:'.$request->ip(), 'max' => 10, 'decay' => 60],
            ['key' => 'extension:login:email-ip:'.$emailHash.':'.$request->ip(), 'max' => 5, 'decay' => 60],
        ];
    }
}
