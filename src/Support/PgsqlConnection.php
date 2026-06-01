<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Support;

final readonly class PgsqlConnection
{
    public function __construct(
        public string $host,
        public string $port,
        public string $database,
        public string $username,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $connection
     */
    public static function fromArray(array $connection): self
    {
        return new self(
            host: self::value($connection, 'host'),
            port: self::value($connection, 'port'),
            database: self::value($connection, 'database'),
            username: self::value($connection, 'username'),
            password: self::value($connection, 'password'),
        );
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private static function value(array $connection, string $key): string
    {
        $value = $connection[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
