<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;

class ApiRequestLogger
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('api_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        $client = $request->attributes->get('api_client');
        $key = $request->attributes->get('api_key');

        ApiRequestLog::query()->create([
            'api_client_id' => $client?->id,
            'api_key_id' => $key?->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200,
            'ip_address' => $request->ip(),
            'origin' => $request->headers->get('Origin'),
            'referer' => $request->headers->get('Referer'),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'response_time_ms' => (int) round((microtime(true) - (float) $request->attributes->get('api_started_at', microtime(true))) * 1000),
            'response_size' => strlen((string) $response->getContent()),
            'request_cost' => (int) $request->attributes->get('api_request_cost', 1),
        ]);
    }
}
