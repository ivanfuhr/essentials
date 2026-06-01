<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Loggers\Github\Issues\Formatters\StructuredDataFormatter;

beforeEach(function (): void {
    $this->formatter = new StructuredDataFormatter;
});

it('returns empty string for null data', function (): void {
    expect($this->formatter->format(null))->toBe('');
});

it('returns empty string for empty array', function (): void {
    expect($this->formatter->format([]))->toBe('');
});

it('formats array data as JSON code block', function (): void {
    $data = [
        'key' => 'value',
        'nested' => ['inner' => 'data'],
    ];

    $result = $this->formatter->format($data);

    expect($result)
        ->toContain('```json')
        ->toContain('"key": "value"')
        ->toContain('"nested"')
        ->toContain('"inner": "data"')
        ->toContain('```');
});
