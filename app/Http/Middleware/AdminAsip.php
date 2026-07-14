<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAsip
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('adminasip')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
