<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use IvanFuhr\Essentials\Commands\Concerns\InteractsWithCommandInput;
use IvanFuhr\Essentials\Support\DatabaseBackup;
use IvanFuhr\Essentials\Support\PgsqlConnection;
use Symfony\Component\Process\Process;

final class DatabaseBackupCommand extends Command
{
    use InteractsWithCommandInput;

    protected $signature = 'db:backup {--connection= : Connection name (PostgreSQL only)}';

    protected $description = 'Create a PostgreSQL backup using pg_dump.';

    public function handle(): int
    {
        try {
            $connection = DatabaseBackup::resolvePgsqlConnection($this->optionalStringOption('connection'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        $backup = DatabaseBackup::forConfiguredDisk();
        $disk = $backup->disk();
        $filename = $this->buildFilename($connection->database);
        $remotePath = $backup->remotePath($filename);
        $tempPath = $this->temporaryPath($filename);

        $command = [
            $this->pgDumpBinary(),
            '--file='.$tempPath,
            '--format=custom',
            '--host='.$connection->host,
            '--port='.$connection->port,
            '--username='.$connection->username,
            '--no-owner',
            '--no-privileges',
            $connection->database,
        ];

        $process = new Process($command, null, $this->pgEnv($connection));
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput() ?: $process->getOutput());
            @unlink($tempPath);

            return self::FAILURE;
        }

        $handle = fopen($tempPath, 'r');

        if ($handle === false) {
            $this->error('Failed to open temporary file for upload.');
            @unlink($tempPath);

            return self::FAILURE;
        }

        $disk->put($remotePath, $handle);
        fclose($handle);
        @unlink($tempPath);

        $this->info(sprintf('Backup created successfully at: %s', $remotePath));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function pgEnv(PgsqlConnection $connection): array
    {
        return [
            'PGPASSWORD' => $connection->password,
        ];
    }

    private function pgDumpBinary(): string
    {
        $binary = config('essentials.backup.pg_dump_binary', 'pg_dump');

        return is_scalar($binary) ? (string) $binary : 'pg_dump';
    }

    private function buildFilename(string $database): string
    {
        return sprintf('%s-%s.dump', $database, now()->format('Ymd_His'));
    }

    private function temporaryPath(string $filename): string
    {
        $tempDir = storage_path('app/tmp');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir.DIRECTORY_SEPARATOR.$filename;
    }
}
