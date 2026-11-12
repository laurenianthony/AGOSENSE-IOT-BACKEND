<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

class RestoreDatabase extends Command
{
    protected $signature = 'db:restore {--force : Skip the confirmation prompt and replace existing database objects}';

    protected $description = 'Restore database/backups/backup.sql into the configured PostgreSQL database.';

    public function handle(): int
    {
        $driver = config('database.default');
        $connection = config('database.connections.'.$driver, []);

        if ($driver !== 'pgsql') {
            $this->error('The configured default database connection must be PostgreSQL (DB_CONNECTION=pgsql).');

            return self::FAILURE;
        }

        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            if (! isset($connection[$key]) || $connection[$key] === '') {
                $this->error('The PostgreSQL database configuration is missing DB_'.strtoupper($key).'.');

                return self::FAILURE;
            }
        }

        $backupPath = database_path('backups/backup.sql');
        $backupSize = is_file($backupPath) ? filesize($backupPath) : false;
        if ($backupSize === false || $backupSize === 0) {
            $this->error('No non-empty backup file was found at '.$backupPath.'. Run php artisan db:backup first.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            "This will replace existing database objects in '{$connection['database']}'. Continue?",
        )) {
            $this->warn('Database restore cancelled.');

            return self::SUCCESS;
        }

        $process = new Process([
            'psql',
            '--no-password',
            '--set=ON_ERROR_STOP=1',
            '--single-transaction',
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--username='.$connection['username'],
            '--dbname='.$connection['database'],
            '--file='.$backupPath,
        ], base_path(), [
            'PGPASSWORD' => (string) $connection['password'],
            'PGSSLMODE' => (string) ($connection['sslmode'] ?? 'prefer'),
        ]);
        $process->setTimeout(null);
        try {
            $process->run();
        } catch (ProcessStartFailedException) {
            $this->error('Could not start psql. Install the PostgreSQL client tools and ensure psql is available on PATH.');

            return self::FAILURE;
        }

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $this->error('Database restore failed: '.($errorOutput !== '' ? $errorOutput : 'psql exited with status '.$process->getExitCode().'.'));

            return self::FAILURE;
        }

        $this->info("Database restored from {$backupPath}.");

        return self::SUCCESS;
    }
}
