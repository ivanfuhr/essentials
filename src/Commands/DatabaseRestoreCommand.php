<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use IvanFuhr\Essentials\Commands\Concerns\InteractsWithCommandInput;
use IvanFuhr\Essentials\Support\DatabaseBackup;
use IvanFuhr\Essentials\Support\PgsqlConnection;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

final class DatabaseRestoreCommand extends Command
{
    use InteractsWithCommandInput;

    protected $signature = 'db:restore {backup? : Absolute path or filename inside the backups folder} {--connection= : Connection name (PostgreSQL only)} {--force : Skip confirmation}';

    protected $description = 'Restore a PostgreSQL backup using pg_restore.';

    public function handle(): int
    {
        $input = $this->optionalStringArgument('backup');

        try {
            $connection = DatabaseBackup::resolvePgsqlConnection($this->optionalStringOption('connection'));

            if ($input === null) {
                $input = $this->selectBackup(DatabaseBackup::forConfiguredDisk());
            }

            $backupFile = $this->resolveLocalBackupPath($input);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        if (! $this->runSafetyBackup($this->optionalStringOption('connection'))) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This operation will replace the current database. Continue?')) {
            $this->info('Restore cancelled.');

            return self::SUCCESS;
        }

        $command = [
            $this->pgRestoreBinary(),
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--host='.$connection->host,
            '--port='.$connection->port,
            '--username='.$connection->username,
            '--dbname='.$connection->database,
            $backupFile,
        ];

        $process = new Process($command, null, $this->pgEnv($connection));
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput() ?: $process->getOutput();

            if (str_contains($errorOutput, 'unsupported version')) {
                $this->error('The installed pg_restore is older than the backup file format.');
                $this->line('Rebuild the app image with PostgreSQL client 17+ (matching the database server), or restore using the postgres container.');
            }

            $this->error($errorOutput);
            $this->cleanupTemp($backupFile, $input);

            return self::FAILURE;
        }

        $this->cleanupTemp($backupFile, $input);

        $this->info('Restore completed successfully.');

        return self::SUCCESS;
    }

    private function resolveLocalBackupPath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            if (! is_file($path)) {
                throw new InvalidArgumentException(sprintf('Backup file not found: %s', $path));
            }

            return $path;
        }

        $backup = DatabaseBackup::forConfiguredDisk();
        $disk = $backup->disk();
        $remotePath = $backup->remotePath($path);

        if (! $disk->exists($remotePath)) {
            $this->listAvailableBackups($backup, $path);

            throw new InvalidArgumentException(sprintf('Backup file not found on disk: %s', $remotePath));
        }

        $stream = $disk->readStream($remotePath);

        if (! is_resource($stream)) {
            throw new InvalidArgumentException('Failed to read backup file from disk.');
        }

        $tempPath = $this->temporaryPath(basename($remotePath));
        $target = @fopen($tempPath, 'w');

        if ($target === false) {
            fclose($stream);

            throw new InvalidArgumentException('Failed to create temporary file for restore.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $tempPath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }

    private function temporaryPath(string $filename): string
    {
        $tempDir = storage_path('app/tmp');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir.DIRECTORY_SEPARATOR.getmypid().'-'.$filename;
    }

    private function cleanupTemp(string $backupFile, string $input): void
    {
        if ($this->isAbsolutePath($input)) {
            return;
        }

        if (is_file($backupFile)) {
            @unlink($backupFile);
        }
    }

    private function listAvailableBackups(DatabaseBackup $backup, string $query): void
    {
        $files = $backup->files();

        if ($files === []) {
            $this->warn('No backups found on the configured disk.');

            return;
        }

        $matches = array_values(array_filter(
            $files,
            static fn (string $file): bool => str_contains(basename($file), $query)
        ));

        $list = $matches !== [] ? $matches : $files;
        $list = array_slice($list, 0, 15);

        $this->warn('Backup file not found. Available backups:');

        foreach ($list as $file) {
            $this->line('- '.$file);
        }
    }

    private function selectBackup(DatabaseBackup $backup): string
    {
        $disk = $backup->disk();
        $files = $backup->files();

        if ($files === []) {
            throw new InvalidArgumentException('No backups found on the configured disk.');
        }

        $ordered = collect($files)
            ->map(fn (string $file): array => [
                'file' => $file,
                'timestamp' => $disk->lastModified($file),
            ])
            ->sortByDesc('timestamp')
            ->values()
            ->all();

        $options = [];

        foreach ($ordered as $entry) {
            $file = $entry['file'];
            $time = $entry['timestamp'];

            $label = basename($file).' ('.now()->createFromTimestamp($time)->toDateTimeString().')';
            $options[$file] = $label;
        }

        $selected = select(
            label: 'Select a backup to restore',
            options: $options,
        );

        return basename(is_string($selected) ? $selected : (string) $selected);
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

    private function pgRestoreBinary(): string
    {
        $binary = config('essentials.backup.pg_restore_binary', 'pg_restore');

        return is_scalar($binary) ? (string) $binary : 'pg_restore';
    }

    private function runSafetyBackup(?string $connection): bool
    {
        if ($this->input !== null && $this->option('force')) {
            return true;
        }

        if (! confirm('Do you want to create a safety backup before restoring?', default: true)) {
            return true;
        }

        $result = $this->call('db:backup', [
            '--connection' => $connection,
        ]);

        if ($result !== self::SUCCESS) {
            $this->error('Safety backup failed; restore aborted.');

            return false;
        }

        return true;
    }
}
