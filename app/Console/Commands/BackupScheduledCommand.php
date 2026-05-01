<?php

namespace App\Console\Commands;

use App\Actions\Admin\CleanOldBackupsAction;
use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Models\BackupSetting;
use Illuminate\Console\Command;

class BackupScheduledCommand extends Command
{
    /** @var string */
    protected $signature = 'backup:scheduled';

    /** @var string */
    protected $description = 'Run a scheduled full database backup';

    public function handle(CleanOldBackupsAction $cleanAction): int
    {
        $settings = BackupSetting::resolve();

        if (! $settings->is_enabled) {
            $this->info('Scheduled backups are disabled.');

            return self::SUCCESS;
        }

        $filename = 'full_'.now()->format('Y_m_d_His').'.dump';

        $backup = Backup::create([
            'type' => 'full',
            'format' => 'dump',
            'filename' => $filename,
            'status' => 'pending',
            'is_scheduled' => true,
        ]);

        RunBackupJob::dispatch($backup);

        $cleanAction->handle();

        $this->info('Scheduled backup queued and old backups cleaned.');

        return self::SUCCESS;
    }
}
