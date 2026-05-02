<?php

namespace App\Jobs;

use App\Actions\Admin\BackupDatabaseAction;
use App\Actions\Admin\BackupTenantAction;
use App\Models\Backup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public Backup $backup)
    {
        $this->onQueue('backups');
    }

    public function handle(BackupTenantAction $tenantAction, BackupDatabaseAction $databaseAction): void
    {
        match ($this->backup->type) {
            'tenant' => $tenantAction->handle($this->backup, $this->backup->tenant),
            'full' => $databaseAction->handle($this->backup),
        };
    }

    public function failed(Throwable $exception): void
    {
        $this->backup->markFailed($exception->getMessage());
    }
}
