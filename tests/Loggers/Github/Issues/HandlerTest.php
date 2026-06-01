<?php

declare(strict_types=1);

namespace Tests\Issues;

use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use RuntimeException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use IvanFuhr\Essentials\Loggers\Github\Issues\Formatters\IssueFormatter;
use IvanFuhr\Essentials\Loggers\Github\Issues\Handler;
use Monolog\Level;
use Monolog\LogRecord;

function createHandler(): Handler
{
    $handler = new Handler(
        repo: 'test/repo',
        token: 'test-token',
        labels: [],
        level: Level::Debug,
        bubble: true
    );

    $handler->setFormatter(app()->make(IssueFormatter::class));

    return $handler;
}

function createRecord(): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: [],
        extra: ['github_issue_signature' => 'test-signature']
    );
}

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('it creates new github issue when no duplicate exists', function (): void {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1]),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);

    Http::assertSent(fn (Request $request) => str($request->url())->endsWith('/repos/test/repo/issues'));
});

test('it comments on existing github issue', function (): void {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => [['number' => 1]]]),
        'github.com/repos/test/repo/issues/1/comments' => Http::response(['id' => 1]),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);

    Http::assertSent(fn ($request) => str($request->url())->endsWith('/issues/1/comments'));
});

test('it includes signature in issue search', function (): void {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1]),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);

    Http::assertSent(fn ($request): bool => str($request->url())->contains('/search/issues')
        && str_contains((string) $request->data()['q'], 'test-signature'));
});

test('it throws exception when issue search fails', function (): void {
    Http::fake([
        'github.com/search/issues*' => Http::response(['error' => 'Failed'], 500),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);
})->throws(RequestException::class, exceptionCode: 500);

test('it throws exception when issue creation fails', function (): void {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['error' => 'Failed'], 500),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);
})->throws(RequestException::class, exceptionCode: 500);

test('it throws exception when comment creation fails', function (): void {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => [['number' => 1]]]),
        'github.com/repos/test/repo/issues/1/comments' => Http::response(['error' => 'Failed'], 500),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);
})->throws(RequestException::class, exceptionCode: 500);

test('it throws when the record is not formatted', function (): void {
    $handler = createHandler();
    $record = createRecord();

    $write = (new \ReflectionClass($handler))->getMethod('write');
    $write->setAccessible(true);

    expect(fn () => $write->invoke($handler, $record))
        ->toThrow(RuntimeException::class, 'Record must be formatted with');
});

test('it throws when github issue signature is missing during search', function (): void {
    $handler = createHandler();
    $record = createRecord()->with(extra: []);

    $findExistingIssue = (new \ReflectionClass($handler))->getMethod('findExistingIssue');
    $findExistingIssue->setAccessible(true);

    expect(fn () => $findExistingIssue->invoke($handler, $record))
        ->toThrow(RuntimeException::class, 'Record is missing github_issue_signature');
});

test('it creates fallback issue when 4xx error occurs', function (): void {
    $errorMessage = 'Validation failed for the issue';

    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::sequence()
            ->push(['error' => $errorMessage], 422)
            ->push(['number' => 1]),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);

    Http::assertSent(fn ($request): bool => str($request->url())->endsWith('/repos/test/repo/issues')
        && ! str_contains((string) $request->data()['title'], '[GitHub Issue Error]'));

    Http::assertSent(fn ($request): bool => str($request->url())->endsWith('/repos/test/repo/issues')
        && str_contains((string) $request->data()['title'], '[GitHub Issue Error]')
        && str_contains((string) $request->data()['body'], $errorMessage)
        && in_array('github-integration-error', $request->data()['labels']));
});
