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
        $traceId = $request->header('X-Amzn-Trace-Id')
            ?: $request->header('X-Trace-Id')
            ?: $request->header('X-Request-Id')
            ?: Uuid::uuid4()->toString();

        App::instance('trace-id', $traceId);

        $response = $next($request);

        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
