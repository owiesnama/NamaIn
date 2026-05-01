<?php

namespace App\Actions\Admin;

use App\Models\Backup;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class BackupDatabaseAction
{
    public function handle(Backup $backup): void
    {
        $backup->markRunning();

        $directory = storage_path('app/backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $config = config('database.connections.pgsql');

        $command = [
            'pg_dump',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--dbname='.$config['database'],
            '--format=custom',
            '--file='.$backup->path,
        ];

        $result = Process::env(['PGPASSWORD' => $config['password'] ?? ''])
            ->timeout(600)
            ->run($command);

        if ($result->failed()) {
            throw new RuntimeException('pg_dump failed: '.$result->errorOutput());
        }

        $backup->markCompleted();
    }
}
