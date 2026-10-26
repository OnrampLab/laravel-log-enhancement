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
    public function it_uses_x_request_id_as_primary_header()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('X-Request-Id', 'client-trace-id-123');

        $response = $this->middleware->handle($request, function () {
            return new Response();
        });

        $this->assertEquals('client-trace-id-123', App::make('trace-id'));
        $this->assertEquals('client-trace-id-123', $response->headers->get('X-Request-Id'));
    }

    /**
     * @test
     */
    public function it_prefers_x_request_id_over_x_amzn_trace_id()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('X-Request-Id', 'client-trace-id-123');
        $request->headers->set('X-Amzn-Trace-Id', 'Root=1-67841bd3-169877302925239a05860005');

        $response = $this->middleware->handle($request, function () {
            return new Response();
        });

        $this->assertEquals('client-trace-id-123', App::make('trace-id'));
        $this->assertEquals('client-trace-id-123', $response->headers->get('X-Request-Id'));
    }

    /**
     * @test
     */
    public function it_falls_back_to_x_amzn_trace_id_when_no_x_request_id()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('X-Amzn-Trace-Id', 'Root=1-67841bd3-169877302925239a05860005');

        $response = $this->middleware->handle($request, function () {
            return new Response();
        });

        $this->assertEquals('Root=1-67841bd3-169877302925239a05860005', App::make('trace-id'));
        $this->assertEquals('Root=1-67841bd3-169877302925239a05860005', $response->headers->get('X-Request-Id'));
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
