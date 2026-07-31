<?php

namespace App\Http\Middleware;

use App\Support\ApiResponder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ExtensionRateLimit
{
    public function handle(
        Request $request,
        Closure $next,
        string $type = 'mobile-read',
    ): Response {
        $limits = $this->limitsFor($request, $type);

        foreach ($limits as $limit) {
            if (! RateLimiter::tooManyAttempts(
                $limit['key'],
                $limit['max'],
            )) {
                continue;
            }

            $retryAfter = max(
                1,
                RateLimiter::availableIn($limit['key']),
            );

            $response = ApiResponder::error(
                $this->messageFor($type),
                [
                    'rate_limit' => [
                        'category' => $type,
                        'retry_after' => $retryAfter,
                    ],
                ],
                429,
            );

            $response->headers->set(
                'Retry-After',
                (string) $retryAfter,
            );

            $response->headers->set(
                'X-RateLimit-Limit',
                (string) $limit['max'],
            );

            $response->headers->set(
                'X-RateLimit-Remaining',
                '0',
            );

            $response->headers->set(
                'X-RateLimit-Category',
                $type,
            );

            return $response;
        }

        foreach ($limits as $limit) {
            RateLimiter::hit(
                $limit['key'],
                $limit['decay'],
            );
        }

        $response = $next($request);

        if ($limits !== []) {
            $primary = $limits[0];

            $remaining = RateLimiter::remaining(
                $primary['key'],
                $primary['max'],
            );

            $response->headers->set(
                'X-RateLimit-Limit',
                (string) $primary['max'],
            );

            $response->headers->set(
                'X-RateLimit-Remaining',
                (string) max(0, $remaining),
            );

            $response->headers->set(
                'X-RateLimit-Category',
                $type,
            );
        }

        return $response;
    }

    private function limitsFor(
        Request $request,
        string $type,
    ): array {
        $identity = $this->identity($request);
        $ip = $request->ip() ?: 'unknown';

        return match ($type) {
            'login' => $this->loginLimits($request, $ip),

            'register' => [
                $this->limit(
                    "mobile:register:ip:{$ip}",
                    5,
                    3600,
                ),
            ],

            'google-auth' => [
                $this->limit(
                    "mobile:google-auth:ip:{$ip}",
                    20,
                    60,
                ),
            ],

            'mobile-read', 'read' => [
                $this->limit(
                    "mobile:read:{$identity}",
                    300,
                    60,
                ),
            ],

            'list-write', 'write', 'delete' => [
                $this->limit(
                    "mobile:list-write:{$identity}",
                    120,
                    60,
                ),
            ],

            'comment-write' => [
                $this->limit(
                    "mobile:comment-write:minute:{$identity}",
                    10,
                    60,
                ),
                $this->limit(
                    "mobile:comment-write:hour:{$identity}",
                    50,
                    3600,
                ),
            ],

            'comment-vote' => [
                $this->limit(
                    "mobile:comment-vote:{$identity}",
                    120,
                    60,
                ),
            ],

            'report-write' => [
                $this->limit(
                    "mobile:report-write:{$identity}",
                    10,
                    3600,
                ),
            ],

            'profile-write' => [
                $this->limit(
                    "mobile:profile-write:{$identity}",
                    30,
                    60,
                ),
            ],

            'follow-write' => [
                $this->limit(
                    "mobile:follow-write:{$identity}",
                    60,
                    60,
                ),
            ],

            'notification-read' => [
                $this->limit(
                    "mobile:notification-read:{$identity}",
                    180,
                    60,
                ),
            ],

            'account-sensitive' => [
                $this->limit(
                    "mobile:account-sensitive:{$identity}",
                    3,
                    3600,
                ),
                $this->limit(
                    "mobile:account-sensitive:ip:{$ip}",
                    10,
                    3600,
                ),
            ],

            default => [
                $this->limit(
                    "mobile:general:{$identity}",
                    120,
                    60,
                ),
            ],
        };
    }

    private function loginLimits(
        Request $request,
        string $ip,
    ): array {
        $email = mb_strtolower(
            trim((string) $request->input('email')),
        );

        $emailHash = hash(
            'sha256',
            $email !== '' ? $email : 'missing',
        );

        return [
            $this->limit(
                "mobile:login:ip:{$ip}",
                10,
                60,
            ),
            $this->limit(
                "mobile:login:email-ip:{$emailHash}:{$ip}",
                5,
                60,
            ),
        ];
    }

    private function identity(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId !== null) {
            return 'user:'.$userId;
        }

        $token = $request->bearerToken();

        if (is_string($token) && $token !== '') {
            return 'token:'.hash('sha256', $token);
        }

        return 'ip:'.($request->ip() ?: 'unknown');
    }

    private function limit(
        string $key,
        int $max,
        int $decay,
    ): array {
        return [
            'key' => $key,
            'max' => $max,
            'decay' => $decay,
        ];
    }

    private function messageFor(string $type): string
    {
        return match ($type) {
            'login' =>
                'Çok fazla giriş denemesi yapıldı. Biraz bekleyip tekrar dene.',

            'register' =>
                'Çok fazla kayıt denemesi yapıldı. Daha sonra tekrar dene.',

            'google-auth' =>
                'Çok fazla Google giriş denemesi yapıldı. Biraz bekleyip tekrar dene.',

            'comment-write' =>
                'Çok hızlı yorum gönderiyorsun. Biraz bekleyip tekrar dene.',

            'comment-vote' =>
                'Çok hızlı yorum oyluyorsun. Biraz bekleyip tekrar dene.',

            'report-write' =>
                'Çok fazla şikâyet gönderdin. Daha sonra tekrar dene.',

            'follow-write' =>
                'Çok hızlı takip işlemi yapıyorsun. Biraz bekleyip tekrar dene.',

            'profile-write' =>
                'Profilini çok hızlı güncelliyorsun. Biraz bekleyip tekrar dene.',

            'account-sensitive' =>
                'Bu güvenlik işlemi için deneme sınırına ulaştın. Daha sonra tekrar dene.',

            default =>
                'Çok fazla istek gönderildi. Biraz bekleyip tekrar dene.',
        };
    }
}
