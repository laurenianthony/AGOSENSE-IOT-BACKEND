<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Back up the configured PostgreSQL database to database/backups/backup.sql.';

    public function handle(): int
    {
        $connection = $this->postgresConnection();

        if ($connection === null) {
            return self::FAILURE;
        }

        $backupPath = database_path('backups/backup.sql');
        $temporaryPath = database_path('backups/.backup.sql.tmp');
        File::ensureDirectoryExists(dirname($backupPath), 0750, true);

        try {
            $process = new Process([
                'pg_dump',
                '--no-password',
                '--format=plain',
                '--schema=public',
                '--no-owner',
                '--no-privileges',
                '--clean',
                '--if-exists',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--dbname='.$connection['database'],
                '--file='.$temporaryPath,
            ], base_path(), $this->postgresEnvironment($connection));
            $process->setTimeout(null);
            try {
                $process->run();
            } catch (ProcessStartFailedException) {
                $this->error('Could not start pg_dump. Install the PostgreSQL client tools and ensure pg_dump is available on PATH.');

                return self::FAILURE;
            }

            if (! $process->isSuccessful()) {
                $errorOutput = trim($process->getErrorOutput());
                $this->error('Database backup failed: '.($errorOutput !== '' ? $errorOutput : 'pg_dump exited with status '.$process->getExitCode().'.'));

                return self::FAILURE;
            }

            $backupSize = is_file($temporaryPath) ? filesize($temporaryPath) : false;
            if ($backupSize === false || $backupSize === 0) {
                $this->error('Database backup failed: pg_dump did not create a non-empty backup file.');

                return self::FAILURE;
            }

            if (! rename($temporaryPath, $backupPath)) {
                $this->error('Database backup completed, but the backup file could not be moved into place.');

                return self::FAILURE;
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        $this->info('Database backup created at '.$backupPath);

        return self::SUCCESS;
    }

    private function postgresConnection(): ?array
    {
        $driver = config('database.default');
        $connection = config('database.connections.'.$driver, []);

        if ($driver !== 'pgsql') {
            $this->error('The configured default database connection must be PostgreSQL (DB_CONNECTION=pgsql).');

            return null;
        }

        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            if (! isset($connection[$key]) || $connection[$key] === '') {
                $this->error('The PostgreSQL database configuration is missing DB_'.strtoupper($key).'.');

                return null;
            }
        }

        return $connection;
    }

    private function postgresEnvironment(array $connection): array
    {
        return [
            'PGPASSWORD' => (string) $connection['password'],
            'PGSSLMODE' => (string) ($connection['sslmode'] ?? 'prefer'),
        ];
    }
}
