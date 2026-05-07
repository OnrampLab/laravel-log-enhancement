<?php

namespace Onramplab\LaravelLogEnhancement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Ramsey\Uuid\Uuid;

class TraceIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $traceId = $request->header('X-Request-Id')
            ?: $request->header('X-Amzn-Trace-Id')
            ?: Uuid::uuid4()->toString();

        App::instance('trace-id', $traceId);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $traceId);

        return $response;
    }
}
