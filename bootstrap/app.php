<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'adminasip' => \App\Http\Middleware\AdminAsip::class,
            'admin.user' => \App\Http\Middleware\AdminUser::class,
            'admin.write' => \App\Http\Middleware\AdminWrite::class,
            'api.log' => \App\Http\Middleware\ApiRequestLogger::class,
            'api.public_limit' => \App\Http\Middleware\PublicApiRateLimit::class,
            'extension.ability' => \App\Http\Middleware\EnsureExtensionTokenAbility::class,
            'extension.limit' => \App\Http\Middleware\ExtensionRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return \App\Support\ApiResponder::error('Parametreler geçersiz.', $exception->errors(), 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return \App\Support\ApiResponder::error('Oturum doğrulaması gerekli.', [], 401);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return \App\Support\ApiResponder::error('Kayıt bulunamadı.', [], 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $response = \App\Support\ApiResponder::error('Çok fazla istek gönderildi. Lütfen biraz sonra tekrar dene.', [], 429);
            $response->headers->set('X-RateLimit-Limit', '60');
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('Retry-After', (string) max(1, $exception->getHeaders()['Retry-After'] ?? 60));

            return $response;
        });
    })->create();
