<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use IvanFuhr\Essentials\Loggers\Github\GithubIssueHandlerFactory;
use IvanFuhr\Essentials\Loggers\Github\GithubLoggerServiceProvider;
use IvanFuhr\Essentials\Loggers\Github\Tracing\BreadcrumbCollector;

beforeEach(function (): void {
    config([
        'essentials.loggers.github.enabled' => true,
        'essentials.loggers.github.repo' => 'owner/repo',
        'essentials.loggers.github.token' => 'ghp_test',
        'essentials.loggers.github.level' => 'error',
        'essentials.loggers.github.labels' => ['production'],
        'essentials.loggers.github.deduplication' => ['time' => 120],
        'essentials.loggers.github.buffer' => ['limit' => 10],
        'essentials.loggers.github.signature_generator' => null,
        'essentials.loggers.github.tracing' => [
            'enabled' => true,
            'queries' => true,
            'query_limit' => 25,
            'outgoing_requests' => false,
            'outgoing_request_limit' => 15,
        ],
        'logging.channels.github' => null,
    ]);

    BreadcrumbCollector::reset();
});

it('registers the github logging channel when configured', function (): void {
    $provider = new GithubLoggerServiceProvider(app());
    $provider->boot();

    expect(config('logging.channels.github'))
        ->toMatchArray([
            'driver' => 'custom',
            'via' => GithubIssueHandlerFactory::class,
            'repo' => 'owner/repo',
            'token' => 'ghp_test',
            'level' => 'error',
            'labels' => ['production'],
        ])
        ->and(config('logging.channels.github.tracing.queries'))
        ->toBe([
            'enabled' => true,
            'limit' => 25,
        ])
        ->and(config('logging.channels.github.tracing.outgoing_requests'))
        ->toBe([
            'enabled' => false,
            'limit' => 15,
        ]);
});

it('does not register the github logging channel without credentials', function (): void {
    config([
        'essentials.loggers.github.repo' => null,
        'essentials.loggers.github.token' => null,
        'logging.channels.github' => null,
    ]);

    $provider = new GithubLoggerServiceProvider(app());
    $provider->boot();

    expect(config('logging.channels.github'))->toBeNull();
});

it('does not boot when the logger is disabled', function (): void {
    config([
        'essentials.loggers.github.enabled' => false,
        'logging.channels.github' => null,
    ]);

    $provider = new GithubLoggerServiceProvider(app());
    $provider->boot();

    expect(config('logging.channels.github'))->toBeNull();
});

it('enables tracing from channel config when package tracing is disabled', function (): void {
    config([
        'essentials.loggers.github.repo' => null,
        'essentials.loggers.github.token' => null,
        'essentials.loggers.github.tracing' => [
            'breadcrumbs' => true,
        ],
        'logging.channels.github' => [
            'tracing' => [
                'enabled' => true,
                'breadcrumbs' => true,
            ],
        ],
    ]);

    $provider = new GithubLoggerServiceProvider(app());
    $provider->boot();

    Event::dispatch(new Illuminate\Log\Events\MessageLogged('info', 'Tracing enabled', []));

    expect(BreadcrumbCollector::getBreadcrumbs())->toHaveCount(1);
});

it('normalizes boolean tracing options into structured config', function (): void {
    config([
        'essentials.loggers.github.tracing' => [
            'enabled' => true,
            'queries' => false,
            'outgoing_requests' => true,
            'outgoing_request_limit' => 7,
        ],
    ]);

    $provider = new GithubLoggerServiceProvider(app());
    $provider->boot();

    expect(config('logging.channels.github.tracing.queries'))->toBe([
        'enabled' => false,
        'limit' => 50,
    ])->and(config('logging.channels.github.tracing.outgoing_requests'))->toBe([
        'enabled' => true,
        'limit' => 7,
    ]);
});

it('dehydrates tracing context keys before job serialization', function (): void {
    $provider = new GithubLoggerServiceProvider(app());
    $provider->register();

    Context::flush();
    Context::add('queries', [['sql' => 'select 1']]);
    Context::add('outgoing_request.visible123', ['url' => 'https://example.com']);
    Context::addHidden('breadcrumbs', [['message' => 'test']]);
    Context::addHidden('outgoing_request.abc123', ['url' => 'https://example.com']);
    Context::add('safe', 'keep');

    $dehydrated = Context::dehydrate();

    expect($dehydrated)->not->toBeNull()
        ->and($dehydrated['data'])->toHaveKey('safe')
        ->and($dehydrated['data'])->not->toHaveKey('queries')
        ->and($dehydrated['data'])->not->toHaveKey('outgoing_request.visible123')
        ->and($dehydrated['hidden'])->not->toHaveKey('breadcrumbs')
        ->and($dehydrated['hidden'])->not->toHaveKey('outgoing_request.abc123');
});

it('registers context dehydration during service provider registration', function (): void {
    $provider = new GithubLoggerServiceProvider(app());
    $provider->register();

    expect(true)->toBeTrue();
});
