<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Loggers\Github\Issues\Formatters\ExceptionFormatter;
use IvanFuhr\Essentials\Loggers\Github\Issues\Formatters\StackTraceFormatter;

beforeEach(function (): void {
    $this->formatter = resolve(ExceptionFormatter::class);
});

test('it formats exception details', function (): void {
    $exception = new RuntimeException('Test exception');
    $record = createLogRecord('Test message', exception: $exception);

    $result = $this->formatter->format($record);

    // Pad the stack trace lines to not mess up the test assertions
    $exceptionTrace = collect(explode("\n", $exception->getTraceAsString()))
        ->map(fn (string $line): string => (new StackTraceFormatter)->padStackTraceLine($line))
        ->join("\n");

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['message', 'simplified_stack_trace', 'full_stack_trace'])
        ->and($result['message'])->toBe('Test exception')
        ->and($result['simplified_stack_trace'])->toContain('[Vendor frames]')
        ->and($result['full_stack_trace'])->toContain($exceptionTrace);
});

test('it returns empty array for non-exception records', function (): void {
    $record = createLogRecord('Test message');

    expect($this->formatter->format($record))->toBeArray()->toBeEmpty();
});

test('it formats exception title', function (): void {
    $exception = new RuntimeException('Test exception');

    $title = $this->formatter->formatTitle($exception, 'ERROR');

    expect($title)
        ->toContain('[ERROR]')
        ->toContain('RuntimeException')
        ->toContain('Test exception');
});

test('it truncates long exception messages in title', function (): void {
    $longMessage = str_repeat('a', 150);
    $exception = new RuntimeException($longMessage);

    $title = $this->formatter->formatTitle($exception, 'ERROR');

    // Title format: [ERROR] RuntimeException in /path/to/file.php:123 - {truncated_message}
    // We check that the message part is truncated
    expect($title)
        ->toContain('[ERROR]')
        ->toContain('RuntimeException')
        ->toContain('...');
});

test('it properly formats exception with stack trace in message', function (): void {
    // Create a custom exception class that mimics our problematic behavior
    $exception = new class('Error message') extends Exception
    {
        public function __construct()
        {
            parent::__construct('The calculation amount [123.45] does not match the expected total [456.78]. in /path/to/app/Calculations/Calculator.php:49
Stack trace:
#0 /path/to/app/Services/PaymentService.php(83): App\\Calculations\\Calculator->calculate()
#1 /vendor/framework/src/Services/TransactionService.php(247): App\\Services\\PaymentService->process()');
        }
    };

    $record = createLogRecord('Test message', exception: $exception);

    $result = $this->formatter->format($record);

    // The formatter should extract just the actual error message without the file path or stack trace
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['message', 'simplified_stack_trace', 'full_stack_trace'])
        ->and($result['message'])->toBe('The calculation amount [123.45] does not match the expected total [456.78].')
        ->and($result['simplified_stack_trace'])->toContain('[stacktrace]')
        ->and($result['simplified_stack_trace'])->toContain('App\\Calculations\\Calculator->calculate()');
});

test('it handles exceptions with string in context', function (): void {
    // Create a generic exception string
    $exceptionString = 'The calculation amount [123.45] does not match the expected total [456.78]. in /path/to/app/Calculations/Calculator.php:49
Stack trace:
#0 /path/to/app/Services/PaymentService.php(83): App\\Calculations\\Calculator->calculate()
#1 /vendor/framework/src/Services/TransactionService.php(247): App\\Services\\PaymentService->process()';

    // Create a record with a string in the exception context
    $record = createLogRecord('Test message', ['exception' => $exceptionString]);

    $result = $this->formatter->format($record);

    // Should extract just the clean message
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['message', 'simplified_stack_trace', 'full_stack_trace'])
        ->and($result['message'])->toBe('The calculation amount [123.45] does not match the expected total [456.78].')
        ->and($result['simplified_stack_trace'])->toContain('[stacktrace]')
        ->and($result['simplified_stack_trace'])->toContain('App\\Calculations\\Calculator->calculate()');
});

test('it creates different simplified and full stack traces', function (): void {
    // Create a string exception with vendor frames
    // Use paths that will be detected as vendor frames after base_path() replacement
    $exceptionString = 'Test exception message
Stack trace:
#0 /app/Services/TestService.php(25): App\\Services\\TestService->handle()
#1 /vendor/laravel/framework/src/Foundation/Application.php(1235): App\\Services\\TestService->process()
#2 /vendor/spatie/package/src/File.php(100): Illuminate\\Foundation\\Application->run()
#3 /app/Http/Controllers/TestController.php(50): Spatie\\Package\\File->execute()';

    $record = createLogRecord('Test message', ['exception' => $exceptionString]);
    $result = $this->formatter->format($record);

    // Simplified trace should collapse vendor frames
    expect($result['simplified_stack_trace'])
        ->toContain('/app/Services/TestService.php')
        ->toContain('/app/Http/Controllers/TestController.php')
        ->toContain('[Vendor frames]')
        ->not->toContain('/vendor/laravel/framework/src/Foundation/Application.php')
        ->not->toContain('/vendor/spatie/package/src/File.php');

    // Full trace should show all frames
    expect($result['full_stack_trace'])
        ->toContain('/app/Services/TestService.php')
        ->toContain('/app/Http/Controllers/TestController.php')
        ->toContain('/vendor/laravel/framework/src/Foundation/Application.php')
        ->toContain('/vendor/spatie/package/src/File.php')
        ->not->toContain('[Vendor frames]');

    // They should be different
    expect($result['simplified_stack_trace'])->not->toBe($result['full_stack_trace']);
});

test('it strips stack trace prefix from string exceptions', function (): void {
    $exceptionString = 'Error message
Stack trace:
#0 /app/Services/Service.php(25): App\\Services\\Service->handle()
#1 /vendor/package/src/File.php(10): App\\Services\\Service->process()';

    $record = createLogRecord('Test message', ['exception' => $exceptionString]);
    $result = $this->formatter->format($record);

    // Should not contain "Stack trace:" in the formatted output since we add our own "[stacktrace]" tag
    expect($result['simplified_stack_trace'])
        ->toContain('[stacktrace]')
        ->not->toContain('Stack trace:');

    expect($result['full_stack_trace'])
        ->toContain('[stacktrace]')
        ->not->toContain('Stack trace:');
});

test('it formats batches of records', function (): void {
    $records = [
        createLogRecord('First', exception: new RuntimeException('One')),
        createLogRecord('Second'),
    ];

    expect($this->formatter->formatBatch($records))->toHaveCount(2);
});

test('it strips exception class prefixes from messages', function (): void {
    $exception = new RuntimeException('RuntimeException: Something went wrong');
    $record = createLogRecord('Test', exception: $exception);

    expect($this->formatter->format($record)['message'])->toBe('Something went wrong');
});

test('it handles string exceptions without stack trace markers', function (): void {
    $record = createLogRecord('Test message', ['exception' => 'Plain failure message']);

    expect($this->formatter->format($record))->toBe([]);
});

test('it handles malformed string exceptions via the fallback formatter', function (): void {
    $method = (new ReflectionClass($this->formatter))->getMethod('formatExceptionString');

    $result = $method->invoke($this->formatter, 'Broken trace format');

    expect($result['message'])->toBe('Broken trace format')
        ->and($result['simplified_stack_trace'])->toContain('Broken trace format');
});

test('it extracts hash-prefixed stack traces from string exceptions', function (): void {
    $exceptionString = 'Failure happened
#0 /app/Services/Service.php(25): App\\Services\\Service->handle()
#1 /vendor/package/src/File.php(10): App\\Services\\Service->process()';

    $record = createLogRecord('Test message', ['exception' => $exceptionString]);
    $result = $this->formatter->format($record);

    expect($result['message'])->toBe('Failure happened')
        ->and($result['simplified_stack_trace'])->toContain('App\\Services\\Service->handle()');
});
