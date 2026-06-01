<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use IvanFuhr\Essentials\Loggers\Github\Tracing\OutgoingRequestSendingCollector;

beforeEach(function (): void {
    $this->collector = new OutgoingRequestSendingCollector;
    Config::set('logging.channels.github.tracing.outgoing_requests', ['enabled' => true, 'limit' => 5]);
});

afterEach(function (): void {
    Context::flush();
});

it('tracks outgoing request sending', function (): void {
    $psrRequest = new PsrRequest('GET', 'https://api.example.com/test', ['Authorization' => 'Bearer token']);
    $request = new Request($psrRequest);

    $sendingEvent = new RequestSending($request);
    ($this->collector)($sendingEvent);

    $requestId = spl_object_hash($request);
    $requestData = Context::getHidden('outgoing_request.'.$requestId);

    expect($requestData)->not->toBeNull();
    expect($requestData)->toHaveKeys(['url', 'method', 'headers', 'body', 'started_at']);
    expect($requestData['url'])->toBe('https://api.example.com/test');
    expect($requestData['method'])->toBe('GET');
    expect($requestData['started_at'])->toBeNumeric();
});

it('does not track when disabled', function (): void {
    Config::set('logging.channels.github.tracing.outgoing_requests', ['enabled' => false]);

    $psrRequest = new PsrRequest('GET', 'https://api.example.com/test');
    $request = new Request($psrRequest);

    $sendingEvent = new RequestSending($request);
    ($this->collector)($sendingEvent);

    $requestId = spl_object_hash($request);
    expect(Context::hasHidden('outgoing_request.'.$requestId))->toBeFalse();
});
