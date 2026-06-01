<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use IvanFuhr\Essentials\Loggers\Github\Tracing\UserDataCollector;

beforeEach(function (): void {
    $this->collector = new UserDataCollector;
    UserDataCollector::flush();
});

afterEach(function (): void {
    // Reset to default resolver
    UserDataCollector::setUserDataResolver(null);
    UserDataCollector::flush();
    Context::flush();
});

it('collects default user data', function (): void {
    // Arrange
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $user->name = 'John Doe';
    $user->email = 'john@example.com';

    $event = new Authenticated('web', $user);

    // Act
    ($this->collector)($event);

    // Assert
    $userData = Context::get('user');
    expect($userData)->toHaveKey('id');
    expect($userData)->toHaveKey('authenticated');
    expect($userData['id'])->toBe(1);
    expect($userData['authenticated'])->toBeTrue();
    expect($userData['name'])->toBe('John Doe');
    expect($userData['email'])->toBe('john@example.com');
});

it('uses custom user data resolver', function (): void {
    // Arrange
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $event = new Authenticated('web', $user);

    UserDataCollector::setUserDataResolver(fn ($user): array => ['custom' => 'data']);

    // Act
    ($this->collector)($event);

    // Assert
    $userData = Context::get('user');
    expect($userData)->toHaveKey('custom');
    expect($userData['custom'])->toBe('data');
    expect($userData)->toHaveKey('id');
    expect($userData['id'])->toBe(1);
});

it('collects user data on demand when not authenticated', function (): void {
    Auth::shouldReceive('check')->once()->andReturn(false);

    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData)->toBe(['authenticated' => false]);
});

it('remembers user on authenticated event', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(42);
    $user->name = null;
    $user->email = null;

    $event = new Authenticated('web', $user);
    ($this->collector)($event);

    // Verify user is remembered
    $userData = Context::get('user');
    expect($userData['id'])->toBe(42);
});

it('remembers user on logout for exception context', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(99);
    $user->name = 'Logging Out User';
    $user->email = 'logout@example.com';

    $event = new Logout('web', $user);
    $this->collector->handleLogout($event);

    // Now when we collect, it should use the remembered user
    Auth::shouldReceive('check')->andReturn(false);
    Context::flush();
    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData['id'])->toBe(99);
    expect($userData['name'])->toBe('Logging Out User');
});

it('flushes remembered user data', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $user->name = 'Test';
    $user->email = 'test@example.com';

    UserDataCollector::rememberUser($user);
    UserDataCollector::flush();

    // After flush, should not have remembered user
    Auth::shouldReceive('check')->andReturn(false);
    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData)->toBe(['authenticated' => false]);
});

it('handles exceptions during user resolution gracefully', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andThrow(new Exception('Database error'));

    UserDataCollector::rememberUser($user);
    Auth::shouldReceive('check')->andReturn(false);
    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData)->toHaveKey('authenticated');
    expect($userData['authenticated'])->toBeTrue();
    expect($userData['id'])->toBeNull();
});

it('caches resolved user details', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    // getAuthIdentifier should only be called once due to caching
    $user->shouldReceive('getAuthIdentifier')->once()->andReturn(1);
    $user->name = 'Cached User';
    $user->email = 'cached@example.com';

    $event = new Authenticated('web', $user);
    ($this->collector)($event);

    // Second call should use cached data
    Context::flush();
    Auth::shouldReceive('check')->andReturn(false);
    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData['id'])->toBe(1);
});

it('exposes the default user data resolver', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(5);
    $user->name = 'Default User';
    $user->email = 'default@example.com';

    $resolver = $this->collector->getUserDataResolver();
    $data = $resolver($user);

    expect($data)->toMatchArray([
        'id' => 5,
        'authenticated' => true,
        'name' => 'Default User',
        'email' => 'default@example.com',
    ]);
});

it('falls back when custom resolver throws', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(9);

    UserDataCollector::setUserDataResolver(fn (): array => throw new RuntimeException('Resolver failed'));
    UserDataCollector::rememberUser($user);

    Auth::shouldReceive('check')->andReturn(false);
    $this->collector->collect();

    expect(Context::get('user'))->toBe([
        'authenticated' => true,
        'id' => 9,
    ]);
});

it('handles auth facade failures during collection', function (): void {
    Auth::shouldReceive('check')->andThrow(new RuntimeException('Auth unavailable'));

    $this->collector->collect();

    expect(Context::get('user'))->toBe(['authenticated' => false]);
});
