<?php

namespace App\Http\Middleware;

use App\Support\ApiResponder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExtensionTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (! $request->user()?->tokenCan($ability)) {
            return ApiResponder::error('Bu işlem için yetkin yok.', [], 403);
        }

        return $next($request);
    }
}
