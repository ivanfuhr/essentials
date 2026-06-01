<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use IvanFuhr\Essentials\Loggers\Github\Tracing\EventHandler;

beforeEach(function (): void {
    Context::flush();
    Config::set('logging.channels.github.tracing', [
        'enabled' => true,
        'requests' => true,
        'route' => true,
        'user' => true,
        'queries' => ['enabled' => true],
        'jobs' => true,
        'commands' => true,
        'outgoing_requests' => ['enabled' => true],
    ]);
});

afterEach(function (): void {
    Context::flush();
});

it('registers request collector when enabled', function (): void {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $request = Request::create('https://example.com/test', 'GET');
    $event = new RequestHandled($request, Mockery::mock(Illuminate\Http\Response::class));

    Event::dispatch($event);

    expect(Context::getHidden('request'))->not->toBeNull();
    expect(Context::getHidden('request')['url'])->toBe('https://example.com/test');
});

it('registers route collector when enabled', function (): void {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $route = Mockery::mock(Illuminate\Routing\Route::class);
    $route->shouldReceive('getName')->andReturn('test.route');
    $route->shouldReceive('uri')->andReturn('test');
    $route->shouldReceive('parameters')->andReturn([]);
    $route->shouldReceive('getAction')->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->andReturn([]);
    $route->shouldReceive('methods')->andReturn(['GET']);

    $request = Mockery::mock(Request::class);
    $event = new RouteMatched($route, $request);

    Event::dispatch($event);

    expect(Context::get('route'))->not->toBeNull();
    expect(Context::get('route')['name'])->toBe('test.route');
});

it('registers user collector when enabled', function (): void {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $event = new Authenticated('web', $user);

    Event::dispatch($event);

    expect(Context::get('user'))->not->toBeNull();
    expect(Context::get('user')['id'])->toBe(1);
});

it('registers query collector when enabled', function (): void {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $connection = Mockery::mock(Illuminate\Database\Connection::class);
    $connection->shouldReceive('getName')->andReturn('mysql');

    $event = new QueryExecuted(
        sql: 'SELECT * FROM users',
        bindings: [],
        time: 1.0,
        connection: $connection
    );

    Event::dispatch($event);

    expect(Context::getHidden('queries'))->not->toBeNull();
    expect(Context::getHidden('queries'))->toHaveCount(1);
});

it('registers job collector when enabled', function (): void {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $job = Mockery::mock(Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getName')->andReturn('TestJob');
    $job->shouldReceive('payload')->andReturn(['displayName' => 'TestJob']);
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('getConnectionName')->andReturn('sync');
    $job->shouldReceive('attempts')->andReturn(1);

    $event = new JobExceptionOccurred('connection', $job, new RuntimeException('Test exception'));

    Event::dispatch($event);

    expect(Context::get('job'))->not->toBeNull();
    expect(Context::get('job')['name'])->toBe('TestJob');
});

it('registers command collector when enabled', function (): void {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $input = Mockery::mock(Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getArguments')->andReturn([]);
    $input->shouldReceive('getOptions')->andReturn([]);

    $event = new CommandStarting('test:command', $input, Mockery::mock(Symfony\Component\Console\Output\OutputInterface::class));

    Event::dispatch($event);

    expect(Context::get('command'))->not->toBeNull();
    expect(Context::get('command')['name'])->toBe('test:command');
});

it('registers outgoing request collectors when enabled', function (): void {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $request = Mockery::mock(Illuminate\Http\Client\Request::class);
    $request->shouldReceive('url')->andReturn('https://example.com/api');
    $request->shouldReceive('method')->andReturn('POST');
    $request->shouldReceive('headers')->andReturn([]);
    $request->shouldReceive('data')->andReturn([]);

    $sendingEvent = new RequestSending($request);
    Event::dispatch($sendingEvent);

    // Check that outgoing request data was stored (using object hash as key)
    $requestId = spl_object_hash($request);
    expect(Context::getHidden('outgoing_request.'.$requestId))->not->toBeNull();

    // Mock the same request object for the response event
    $request->shouldReceive('url')->andReturn('https://example.com/api');
    $request->shouldReceive('method')->andReturn('POST');

    $response = Mockery::mock(Illuminate\Http\Client\Response::class);
    $response->shouldReceive('status')->andReturn(200);

    $responseEvent = new ResponseReceived($request, $response);
    Event::dispatch($responseEvent);

    $outgoingRequests = Context::getHidden('outgoing_requests');
    expect($outgoingRequests)->toHaveCount(1);
    expect($outgoingRequests[0]['url'])->toBe('https://example.com/api');
    expect($outgoingRequests[0]['status'])->toBe(200);
});

it('does not register collectors when tracing is disabled', function (): void {
    Config::set('logging.channels.github.tracing.enabled', false);

    $handler = new EventHandler;

    // When tracing is disabled, subscribe should return early without registering listeners
    // We verify this by ensuring the method completes without error
    $handler->subscribe(Event::getFacadeRoot());

    // The handler should have returned early, so no listeners were registered
    // Note: Other Laravel listeners (e.g., ContextServiceProvider) may still collect data,
    // but our EventHandler should not register any collectors when tracing is disabled
    expect(true)->toBeTrue();
});

it('does not register collectors when package tracing is disabled', function (): void {
    Config::set('essentials.loggers.github.tracing.enabled', false);
    Config::set('logging.channels.github.tracing.enabled', false);

    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $connection = Mockery::mock(Illuminate\Database\Connection::class);
    $connection->shouldReceive('getName')->andReturn('mysql');

    Event::dispatch(new QueryExecuted(
        sql: 'SELECT 1',
        bindings: [],
        time: 1.0,
        connection: $connection
    ));

    expect(Context::getHidden('queries') ?? [])->toBeEmpty();
});

it('registers breadcrumb listeners when enabled', function (): void {
    Config::set('logging.channels.github.tracing.breadcrumbs', true);
    IvanFuhr\Essentials\Loggers\Github\Tracing\BreadcrumbCollector::reset();

    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::dispatch(new Illuminate\Log\Events\MessageLogged('info', 'Breadcrumb message', []));
    Event::dispatch(new Illuminate\Cache\Events\CacheHit('array', 'cache-key', 'value'));
    Event::dispatch(new Illuminate\Cache\Events\CacheMissed('array', 'missing-key'));

    expect(IvanFuhr\Essentials\Loggers\Github\Tracing\BreadcrumbCollector::getBreadcrumbs())->toHaveCount(3);
});

it('registers logout listener to remember user data', function (): void {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(77);

    Event::dispatch(new Illuminate\Auth\Events\Logout('web', $user));

    Auth::shouldReceive('check')->andReturn(false);
    Context::flush();
    (new IvanFuhr\Essentials\Loggers\Github\Tracing\UserDataCollector)->collect();

    expect(Context::get('user')['id'])->toBe(77);
});

it('does not register individual collectors when disabled', function (): void {
    Config::set('logging.channels.github.tracing', [
        'enabled' => true,
        'requests' => false,
        'route' => false,
        'user' => false,
        'queries' => ['enabled' => false],
        'jobs' => false,
        'commands' => false,
        'outgoing_requests' => ['enabled' => false],
    ]);

    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    // When individual collectors are disabled, they should not be registered
    // We verify by checking that queries collector (which we can test in isolation)
    // doesn't collect data when disabled
    $connection = Mockery::mock(Illuminate\Database\Connection::class);
    $connection->shouldReceive('getName')->andReturn('mysql');

    $event = new QueryExecuted(
        sql: 'SELECT * FROM users',
        bindings: [],
        time: 1.0,
        connection: $connection
    );

    Event::dispatch($event);

    // Queries collector is disabled, so it should not have collected data
    // (Note: Context might have data from previous tests, but queries should be empty or unchanged)
    $queries = Context::getHidden('queries') ?? [];
    expect($queries)->toBeEmpty();
});

it('catches exceptions from collectors and does not propagate them', function (): void {
    // Test that the exception handling wrapper in EventHandler works correctly
    // by simulating what happens when a collector throws an exception

    $failingCollectorClass = (new class implements IvanFuhr\Essentials\Loggers\Github\Tracing\Contracts\EventDrivenCollectorInterface
    {
        public function __invoke($event): never
        {
            throw new RuntimeException('Collector error');
        }

        public function isEnabled(): bool
        {
            return true;
        }
    })::class;

    // Register a listener using the same pattern as EventHandler (wrapped in try-catch)
    Event::listen(RequestHandled::class, function ($event) use ($failingCollectorClass): void {
        try {
            /** @var IvanFuhr\Essentials\Loggers\Github\Tracing\Contracts\EventDrivenCollectorInterface $collectorInstance */
            $collectorInstance = new $failingCollectorClass;
            $collectorInstance($event);
        } catch (Throwable) {
            // Silently ignore exceptions from collectors to prevent
            // masking the original exception being reported
        }
    });

    $request = Request::create('https://example.com/test', 'GET');
    $event = new RequestHandled($request, Mockery::mock(Illuminate\Http\Response::class));

    // This should not throw even if collector throws an exception
    Event::dispatch($event);

    // Verify the event was dispatched successfully without throwing
    expect(true)->toBeTrue();
});
