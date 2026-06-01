<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Loggers\Github\Tracing\GitInfoDetector;

beforeEach(function (): void {
    GitInfoDetector::resetCache();
});

afterEach(function (): void {
    GitInfoDetector::resetCache();
});

it('detects git information from current repository', function (): void {
    $detector = new GitInfoDetector;
    $info = $detector->detect();

    // We are in a git repo, so at minimum git_hash and git_branch should be present
    expect($info)->toHaveKey('git_hash');
    expect($info)->toHaveKey('git_branch');
    expect($info)->toHaveKey('git_dirty');

    expect($info['git_hash'])->toBeString()->not->toBeEmpty();
    expect($info['git_branch'])->toBeString()->not->toBeEmpty();
    expect($info['git_dirty'])->toBeBool();
});

it('caches results across multiple calls', function (): void {
    $detector = new GitInfoDetector;
    $first = $detector->detect();
    $second = $detector->detect();

    expect($first)->toBe($second);
});

it('resets cache when resetCache is called', function (): void {
    $detector = new GitInfoDetector;
    $first = $detector->detect();

    GitInfoDetector::resetCache();

    $second = $detector->detect();

    // Results should be identical since we are in the same repo
    expect($first)->toBe($second);
});

it('returns short hash format', function (): void {
    $detector = new GitInfoDetector;
    $info = $detector->detect();

    // Short hash is typically 7-12 characters
    expect(mb_strlen($info['git_hash']))->toBeLessThanOrEqual(12);
    expect(mb_strlen($info['git_hash']))->toBeGreaterThanOrEqual(7);
});
