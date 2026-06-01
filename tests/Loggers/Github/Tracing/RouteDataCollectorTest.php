<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use IvanFuhr\Essentials\Loggers\Github\Tracing\RouteDataCollector;

beforeEach(function (): void {
    $this->collector = new RouteDataCollector;
});

afterEach(function (): void {
    Context::flush();
});

it('collects route data', function (): void {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn('users.index');
    $route->shouldReceive('uri')->andReturn('users');
    $route->shouldReceive('parameters')->once()->andReturn(['id' => 123]);
    $route->shouldReceive('getAction')->once()->andReturn([
        'controller' => 'App\Http\Controllers\UserController@index',
    ]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn(['web', 'auth']);
    $route->shouldReceive('methods')->once()->andReturn(['GET', 'HEAD']);

    $request = Mockery::mock(Request::class);
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    $routeData = Context::get('route');

    expect($routeData)->toHaveKeys(['name', 'uri', 'parameters', 'controller', 'middleware', 'methods']);
    expect($routeData['name'])->toBe('users.index');
    expect($routeData['uri'])->toBe('users');
    expect($routeData['parameters'])->toBe(['id' => 123]);
    expect($routeData['controller'])->toBe('App\Http\Controllers\UserController@index');
    expect($routeData['middleware'])->toBe(['web', 'auth']);
    expect($routeData['methods'])->toBe(['GET', 'HEAD']);
});

it('handles route without name', function (): void {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('api/users');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['GET']);

    $request = Mockery::mock(Request::class);
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    $routeData = Context::get('route');

    expect($routeData['name'])->toBeNull();
    expect($routeData['controller'])->toBeNull();
});

it('sets route_summary for normal routes', function (): void {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn('dashboard');
    $route->shouldReceive('uri')->andReturn('dashboard');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['GET']);

    $request = Mockery::mock(Request::class);
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    $routeSummary = Context::get('route_summary');
    expect($routeSummary)->toBe('dashboard');
});

it('uses originating page for livewire routes', function (): void {
    // Set up the originating page context (as would be set by LivewireDataCollector)
    Context::add('livewire_originating_page', '/dashboard');

    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('livewire/message/counter');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['POST']);

    $request = Mockery::mock(Request::class);
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    $routeSummary = Context::get('route_summary');
    expect($routeSummary)->toBe('/dashboard');
});

it('identifies livewire message routes', function (): void {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('livewire/message/some-component');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['POST']);

    $request = Mockery::mock(Request::class);
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    // For Livewire routes without originating page, it should try referer
    // When no referer available, it falls back to the URI
    $routeSummary = Context::get('route_summary');
    expect($routeSummary)->toBeString();
});

it('identifies livewire update routes', function (): void {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('livewire/update');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['POST']);

    $request = Mockery::mock(Request::class);
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    // Livewire update route should be identified
    $routeData = Context::get('route');
    expect($routeData['uri'])->toBe('livewire/update');
});

it('falls back to the referer for livewire routes without originating page context', function (): void {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('livewire/message/counter');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['POST']);

    $request = Request::create('/livewire/message/counter', 'POST');
    $request->headers->set('referer', 'https://example.com/dashboard?tab=1');

    app()->instance('request', $request);

    $event = new RouteMatched($route, $request);
    ($this->collector)($event);

    expect(Context::get('route_summary'))->toBe('/dashboard?tab=1');
});
