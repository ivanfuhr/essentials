<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Loggers\Github\Issues\Formatters\PreviousExceptionFormatter;

beforeEach(function (): void {
    $this->formatter = resolve(PreviousExceptionFormatter::class);
});

test('it returns empty string when exception is not a throwable', function (): void {
    $record = createLogRecord('Test message', ['exception' => 'not-a-throwable']);

    expect($this->formatter->format($record))->toBe('');
});

test('it returns empty string when no previous exception', function (): void {
    $record = createLogRecord('Test message', exception: new RuntimeException('Test exception'));

    $result = $this->formatter->format($record);

    expect($result)->toBe('');
});

test('it formats single previous exception', function (): void {
    $record = createLogRecord('Test message', exception: new RuntimeException(
        'Test exception',
        previous: new RuntimeException('Previous exception')
    ));

    $result = $this->formatter->format($record);

    expect($result)
        ->toContain('Previous Exception #1')
        ->toContain('Previous exception')
        ->toContain('[Vendor frames]')
        ->not->toContain('Additional previous exceptions were truncated');
});

test('it formats multiple previous exceptions up to max limit', function (): void {
    $record = createLogRecord('Test message', exception: new RuntimeException(
        'Test exception',
        previous: new RuntimeException(
            'Previous exception',
            previous: new RuntimeException(
                'Previous exception',
                previous: new RuntimeException('Previous exception')
            )
        )
    ));

    $result = $this->formatter->format($record);

    expect($result)
        ->toContain('Previous Exception #1')
        ->toContain('Previous Exception #2')
        ->toContain('Previous Exception #3')
        ->not->toContain('Additional previous exceptions were truncated');
});

test('it adds truncation note when there are more exceptions than max limit', function (): void {
    $record = createLogRecord('Test message', exception: new RuntimeException(
        'Test exception',
        previous: new RuntimeException(
            'Previous exception',
            previous: new RuntimeException(
                'Previous exception',
                previous: new RuntimeException('Previous exception', previous: new RuntimeException('Truncated exception'))
            )
        )
    ));

    $result = $this->formatter->format($record);

    expect($result)
        ->toContain('Previous Exception #1')
        ->toContain('Previous Exception #2')
        ->toContain('Previous Exception #3')
        ->toContain('Additional previous exceptions were truncated');
});
