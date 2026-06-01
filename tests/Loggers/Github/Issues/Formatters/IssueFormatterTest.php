<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Loggers\Github\Issues\Formatters\Formatted;
use IvanFuhr\Essentials\Loggers\Github\Issues\Formatters\IssueFormatter;

beforeEach(function (): void {
    $this->formatter = app()->make(IssueFormatter::class);
});

test('it formats basic log records', function (): void {
    $record = createLogRecord('Test error message', signature: 'test-signature');

    $formatted = $this->formatter->format($record);

    expect($formatted)
        ->toBeInstanceOf(Formatted::class)
        ->and($formatted->title)->toContain('[ERROR] Test error message')
        ->and($formatted->body)->toContain('**Level:** ERROR')
        ->and($formatted->body)->toContain('Test error message');
});

test('it formats exceptions with file and line information', function (): void {
    $record = createLogRecord('Error occurred', exception: new RuntimeException('Test exception'), signature: 'test-signature');

    $formatted = $this->formatter->format($record);

    expect($formatted->title)
        ->toContain('RuntimeException')
        ->toContain('.php:')
        ->and($formatted->body)
        ->toContain('Test exception')
        ->toContain('<summary>📋 Stack Trace</summary>');
});

test('it truncates long titles', function (): void {
    $longMessage = str_repeat('a', 90);
    $record = createLogRecord($longMessage, signature: 'test-signature');

    $formatted = $this->formatter->format($record);

    expect(mb_strlen((string) $formatted->title))->toBeLessThanOrEqual(100);
});

test('it includes context data in formatted output', function (): void {
    $record = createLogRecord(
        'Test message',
        context: [
            'user_id' => 123,
            'action' => 'login',
        ],
        exception: new RuntimeException('Test exception'),
        signature: 'test-signature',
    );

    $formatted = $this->formatter->format($record);

    expect($formatted->body)
        ->toContain('"user_id": 123')
        ->toContain('"action": "login"');
});

test('it formats stack traces with collapsible vendor frames', function (): void {
    $exception = new Exception('Test exception');
    $reflection = new ReflectionClass($exception);
    $traceProperty = $reflection->getProperty('trace');

    // Set a custom stack trace with both vendor and application frames
    $traceProperty->setValue($exception, [
        [
            'file' => base_path('app/Http/Controllers/TestController.php'),
            'line' => 25,
            'function' => 'testMethod',
            'class' => 'TestController',
        ],
        [
            'file' => base_path('vendor/laravel/framework/src/Testing.php'),
            'line' => 50,
            'function' => 'vendorMethod',
            'class' => 'VendorClass',
        ],
        [
            'file' => base_path('vendor/another/package/src/File.php'),
            'line' => 100,
            'function' => 'anotherVendorMethod',
            'class' => 'AnotherVendorClass',
        ],
        [
            'file' => base_path('app/Services/TestService.php'),
            'line' => 30,
            'function' => 'serviceMethod',
            'class' => 'TestService',
        ],
    ]);

    $record = createLogRecord('Error occurred', exception: $exception, signature: 'test-signature');

    $formatted = $this->formatter->format($record);

    expect($formatted->body)
        ->toContain('app/Http/Controllers/TestController.php')
        ->toContain('app/Services/TestService.php')
        ->toContain('[Vendor frames]');
});

test('it throws when github issue signature is missing', function (): void {
    $record = createLogRecord('Test message');

    expect(fn () => $this->formatter->format($record))
        ->toThrow(RuntimeException::class, 'Record is missing github_issue_signature');
});

test('it formats batches of records', function (): void {
    $records = [
        createLogRecord('First message', signature: 'sig-1'),
        createLogRecord('Second message', signature: 'sig-2'),
    ];

    $formatted = $this->formatter->formatBatch($records);

    expect($formatted)->toHaveCount(2)
        ->and($formatted[0])->toBeInstanceOf(Formatted::class)
        ->and($formatted[1])->toBeInstanceOf(Formatted::class);
});
