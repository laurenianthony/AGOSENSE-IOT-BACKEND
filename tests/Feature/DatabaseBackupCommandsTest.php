<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandsTest extends TestCase
{
    public function test_backup_refuses_non_postgresql_connections(): void
    {
        config(['database.default' => 'sqlite']);

        $this->artisan('db:backup')
            ->expectsOutput('The configured default database connection must be PostgreSQL (DB_CONNECTION=pgsql).')
            ->assertExitCode(1);
    }

    public function test_restore_refuses_non_postgresql_connections(): void
    {
        config(['database.default' => 'sqlite']);

        $this->artisan('db:restore')
            ->expectsOutput('The configured default database connection must be PostgreSQL (DB_CONNECTION=pgsql).')
            ->assertExitCode(1);
    }

    public function test_restore_requires_an_existing_non_empty_backup(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => 'localhost',
            'database.connections.pgsql.port' => '5432',
            'database.connections.pgsql.database' => 'test',
            'database.connections.pgsql.username' => 'test',
            'database.connections.pgsql.password' => 'test',
        ]);

        $backupPath = database_path('backups/backup.sql');
        if (File::exists($backupPath)) {
            $this->markTestSkipped('A real database backup exists; the test will not modify it.');
        }

        $this->artisan('db:restore')
            ->expectsOutput('No non-empty backup file was found at '.$backupPath.'. Run php artisan db:backup first.')
            ->assertExitCode(1);
    }
}
