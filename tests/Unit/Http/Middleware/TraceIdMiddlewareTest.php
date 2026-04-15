<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Onramplab\LaravelLogEnhancement\Http\Middleware\TraceIdMiddleware;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;
use Ramsey\Uuid\Uuid;

class TraceIdMiddlewareTest extends TestCase
{
    /**
     * @var TraceIdMiddleware
     */
    protected $middleware;

    public function setUp(): void
    {
        parent::setUp();
        $this->middleware = new TraceIdMiddleware();
    }

    /**
     * @test
     */
    public function it_uses_existing_x_amzn_trace_id_header()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('X-Amzn-Trace-Id', 'Root=1-67841bd3-169877302925239a05860005');

        $response = $this->middleware->handle($request, function () {
            return new Response();
        });

        $this->assertEquals('Root=1-67841bd3-169877302925239a05860005', App::make('trace-id'));
    }

    /**
     * @test
     */
    public function it_uses_existing_x_trace_id_header()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('X-Trace-Id', 'existing-trace-id-123');

        $response = $this->middleware->handle($request, function () {
            return new Response();
        });

        $this->assertEquals('existing-trace-id-123', App::make('trace-id'));
        $this->assertEquals('existing-trace-id-123', $response->headers->get('X-Request-Id'));
    }

    /**
     * @test
     */
    public function it_falls_back_to_x_request_id_header()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('X-Request-Id', 'existing-request-id-456');

        $response = $this->middleware->handle($request, function () {
            return new Response();
        });

        $this->assertEquals('existing-request-id-456', App::make('trace-id'));
        $this->assertEquals('existing-request-id-456', $response->headers->get('X-Request-Id'));
    }

    /**
     * @test
     */
    public function it_generates_new_uuid_if_no_headers_present()
    {
        $request = Request::create('/', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new Response();
        });

        $traceId = App::make('trace-id');

        $this->assertTrue(Uuid::isValid($traceId));
        $this->assertEquals($traceId, $response->headers->get('X-Request-Id'));
    }
}
