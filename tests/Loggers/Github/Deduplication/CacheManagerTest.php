<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use IvanFuhr\Essentials\Loggers\Github\Deduplication\CacheManager;

use function Pest\Laravel\travel;

beforeEach(function (): void {
    $this->store = 'array';
    $this->prefix = 'test:';
    $this->ttl = 60;

    $this->manager = new CacheManager(
        store: $this->store,
        prefix: $this->prefix,
        ttl: $this->ttl
    );

    Cache::store($this->store)->clear();
});

afterEach(function (): void {
    Carbon::setTestNow();
    Cache::store($this->store)->clear();
});

test('it can add and check signatures', function (): void {
    $signature = 'test-signature';

    expect($this->manager->has($signature))->toBeFalse();

    $this->manager->add($signature);

    expect($this->manager->has($signature))->toBeTrue();
});

test('it expires old entries', function (): void {
    $signature = 'test-signature';
    $this->manager->add($signature);

    // Travel forward in time past TTL
    travel($this->ttl + 1)->seconds();

    expect($this->manager->has($signature))->toBeFalse();
});

test('it keeps valid entries', function (): void {
    $signature = 'test-signature';
    $this->manager->add($signature);

    // Travel forward in time but not past TTL
    travel($this->ttl - 1)->seconds();

    expect($this->manager->has($signature))->toBeTrue();
});

test('it increments occurrence count', function (): void {
    $signature = 'test-signature';

    expect($this->manager->getOccurrenceCount($signature))->toBe(0);

    $count1 = $this->manager->incrementOccurrenceCount($signature);
    expect($count1)->toBe(1);

    $count2 = $this->manager->incrementOccurrenceCount($signature);
    expect($count2)->toBe(2);

    $count3 = $this->manager->incrementOccurrenceCount($signature);
    expect($count3)->toBe(3);

    expect($this->manager->getOccurrenceCount($signature))->toBe(3);
});

test('occurrence count uses separate cache key from dedup signature', function (): void {
    $signature = 'test-signature';

    $this->manager->add($signature);
    $this->manager->incrementOccurrenceCount($signature);

    // The dedup signature should exist
    expect($this->manager->has($signature))->toBeTrue();

    // The occurrence count should be 1
    expect($this->manager->getOccurrenceCount($signature))->toBe(1);

    // Expiring the dedup key should not affect the occurrence counter
    // (they share the same TTL but are independent keys)
    travel($this->ttl + 1)->seconds();

    expect($this->manager->has($signature))->toBeFalse();
    expect($this->manager->getOccurrenceCount($signature))->toBe(0);
});

test('occurrence count tracks different signatures independently', function (): void {
    $sig1 = 'signature-one';
    $sig2 = 'signature-two';

    $this->manager->incrementOccurrenceCount($sig1);
    $this->manager->incrementOccurrenceCount($sig1);
    $this->manager->incrementOccurrenceCount($sig2);

    expect($this->manager->getOccurrenceCount($sig1))->toBe(2);
    expect($this->manager->getOccurrenceCount($sig2))->toBe(1);
});
