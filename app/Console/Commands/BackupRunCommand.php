<?php

namespace App\Console\Commands;

use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Models\Tenant;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    /** @var string */
    protected $signature = 'backup:run
        {--tenant= : Backup a specific tenant by ID}
        {--all : Full database backup}
        {--format=sql : Export format for tenant backups (sql or json)}';

    /** @var string */
    protected $description = 'Run an on-demand database backup';

    public function handle(): int
    {
        if ($this->option('tenant')) {
            return $this->backupTenant();
        }

        if ($this->option('all')) {
            return $this->backupFull();
        }

        $this->error('You must specify --tenant=ID or --all.');

        return self::FAILURE;
    }

    private function backupTenant(): int
    {
        $tenant = Tenant::find($this->option('tenant'));

        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $format = $this->option('format');

        if (! in_array($format, ['sql', 'json'])) {
            $this->error('Format must be sql or json.');

            return self::FAILURE;
        }

        $filename = "tenant_{$tenant->id}_{$tenant->slug}_".now()->format('Y_m_d_His').".{$format}";

        $backup = Backup::create([
            'type' => 'tenant',
            'format' => $format,
            'tenant_id' => $tenant->id,
            'filename' => $filename,
            'status' => 'pending',
        ]);

        RunBackupJob::dispatch($backup);

        $this->info("Backup queued for tenant '{$tenant->name}' ({$format}).");

        return self::SUCCESS;
    }

    private function backupFull(): int
    {
        $filename = 'full_'.now()->format('Y_m_d_His').'.dump';

        $backup = Backup::create([
            'type' => 'full',
            'format' => 'dump',
            'filename' => $filename,
            'status' => 'pending',
        ]);

        RunBackupJob::dispatch($backup);

        $this->info('Full database backup queued.');

        return self::SUCCESS;
    }
}
