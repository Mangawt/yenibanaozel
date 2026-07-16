<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || Gate::forUser($request->user())->denies('admin.view')) {
            abort(404);
        }

        return $next($request);
    }
}
