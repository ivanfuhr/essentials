<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Loggers\Github\Tracing\CallerFrameProcessor;

require __DIR__.'/../../../Support/caller_probe.php';

use function Tests\Support\findCallerFrameThroughProbe;
use function Tests\Support\processLogRecordThroughCallerProbe;

beforeEach(function (): void {
    $this->processor = new CallerFrameProcessor;
});

test('skips processing when exception is present', function (): void {
    $record = createLogRecord('Test', exception: new Exception('Test'));
    $processed = ($this->processor)($record);

    expect($processed->extra)->not->toHaveKey('caller');
});

test('captures caller frame for message-only records', function (): void {
    $record = createLogRecord('Test message');
    $processed = processLogRecordThroughCallerProbe($this->processor, $record);

    expect($processed->extra)
        ->toHaveKey('caller')
        ->and($processed->extra['caller']['file'])->toContain('tests/Support/caller_probe.php');
});

test('normalizes file paths in caller frame', function (): void {
    $record = createLogRecord('Test message');
    $processed = processLogRecordThroughCallerProbe($this->processor, $record);

    expect($processed->extra['caller']['file'])
        ->not->toStartWith(base_path())
        ->toContain('tests/Support/caller_probe.php');
});

test('returns the original record when no caller frame is found', function (): void {
    $record = createLogRecord('Test message');
    $processed = ($this->processor)($record);

    expect($processed->extra)->not->toHaveKey('caller');
});

test('handles empty caller paths gracefully', function (): void {
    $normalizePath = (new ReflectionClass($this->processor))->getMethod('normalizePath');
    $normalizePath->setAccessible(true);

    expect($normalizePath->invoke($this->processor, ''))->toBe('');
});

test('finds caller frames outside vendor and package code', function (): void {
    expect(findCallerFrameThroughProbe($this->processor))->toBeArray()
        ->toHaveKeys(['file', 'func']);
});
