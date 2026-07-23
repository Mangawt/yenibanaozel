<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "frame-ancestors 'self'",
                "img-src 'self' data: blob: https: http:",
                "font-src 'self' data:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self' 'unsafe-inline'",
                "connect-src 'self' https:",
                "form-action 'self'",
                "object-src 'none'",
            ]));
        }

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
