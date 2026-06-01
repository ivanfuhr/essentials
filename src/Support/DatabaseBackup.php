<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final readonly class DatabaseBackup
{
    public function __construct(
        private FilesystemAdapter $disk,
        private string $directory,
    ) {}

    public static function forConfiguredDisk(): self
    {
        $diskName = self::configString('essentials.backup.disk');

        if ($diskName === '') {
            $diskName = self::configString('filesystems.default', 'local');
        }

        $directory = mb_trim(self::configString('essentials.backup.directory', 'backups'), '/');

        $disk = Storage::disk($diskName);

        if (! $disk instanceof FilesystemAdapter) {
            throw new InvalidArgumentException(sprintf('Disk "%s" is not a filesystem adapter.', $diskName));
        }

        if ($directory !== '' && ! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        return new self($disk, $directory);
    }

    public static function resolvePgsqlConnection(?string $connectionName): PgsqlConnection
    {
        $name = $connectionName ?? self::configString('database.default');
        $connection = config(sprintf('database.connections.%s', $name));

        if (! is_array($connection)) {
            throw new InvalidArgumentException(sprintf('Connection "%s" not found.', $name));
        }

        if (($connection['driver'] ?? null) !== 'pgsql') {
            throw new InvalidArgumentException('The selected connection must use the "pgsql" driver.');
        }

        /** @var array<string, mixed> $connection */
        return PgsqlConnection::fromArray($connection);
    }

    public function path(string $filename): string
    {
        $cleaned = mb_ltrim($filename, '/\\');

        return $this->disk->path($this->directory !== '' ? $this->directory.'/'.$cleaned : $cleaned);
    }

    public function remotePath(string $filename): string
    {
        $cleaned = mb_ltrim($filename, '/\\');

        return $this->directory !== '' ? $this->directory.'/'.$cleaned : $cleaned;
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $files = [];

        foreach ($this->disk->allFiles($this->directory) as $file) {
            if (is_string($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function disk(): FilesystemAdapter
    {
        return $this->disk;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    private static function configString(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }
}
