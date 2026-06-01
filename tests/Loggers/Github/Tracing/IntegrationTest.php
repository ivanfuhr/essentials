<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use IvanFuhr\Essentials\Loggers\Github\Tracing\ContextProcessor;
use IvanFuhr\Essentials\Loggers\Github\Tracing\EnvironmentCollector;
use IvanFuhr\Essentials\Loggers\Github\Tracing\QueryCollector;
use IvanFuhr\Essentials\Loggers\Github\Tracing\RequestDataCollector;
use Monolog\Level;

beforeEach(function (): void {
    Context::flush();
});

afterEach(function (): void {
    Context::flush();
});

it('collects multiple context types together', function (): void {
    // Simulate request
    $request = Request::create('https://example.com/test', 'GET');

    $requestEvent = new RequestHandled($request, Mockery::mock(Illuminate\Http\Response::class));
    (new RequestDataCollector)($requestEvent);

    // Simulate query
    $connection = Mockery::mock(Illuminate\Database\Connection::class);
    $connection->shouldReceive('getName')->andReturn('mysql');

    $queryEvent = new QueryExecuted(
        sql: 'SELECT * FROM users',
        bindings: [],
        time: 5.0,
        connection: $connection
    );
    Config::set('logging.channels.github.tracing.queries', ['enabled' => true]);
    (new QueryCollector)($queryEvent);

    // Collect environment
    (new EnvironmentCollector)->collect();

    // Process through ContextProcessor
    $record = createLogRecord('Test error', [], [], Level::Error);
    $processor = new ContextProcessor;
    $processed = $processor($record);

    // Verify all context is present
    expect($processed->context)
        ->toHaveKey('request')
        ->toHaveKey('queries')
        ->toHaveKey('environment');

    expect($processed->context['request']['url'])->toBe('https://example.com/test');
    expect($processed->context['queries'])->toHaveCount(1);
    expect($processed->context['environment'])->toHaveKey('app_env');
});

it('respects configuration when collecting context', function (): void {
    Config::set('logging.channels.github.tracing', [
        'enabled' => true,
        'requests' => false,
        'environment' => false,
        'queries' => ['enabled' => false],
    ]);

    // Request collector won't run because requests is disabled
    // But if it did run, it wouldn't be collected by ContextProcessor
    // Environment should not be collected when disabled

    // Process through ContextProcessor
    $record = createLogRecord('Test error');
    $processor = new ContextProcessor;
    $processed = $processor($record);

    // Environment should not be collected when disabled
    expect($processed->context)->not->toHaveKey('environment');
});
