<?php

namespace App\Actions\Admin;

use App\Models\Backup;
use App\Models\BackupSetting;

class CleanOldBackupsAction
{
    public function handle(): void
    {
        $retentionCount = BackupSetting::resolve()->retention_count;

        $backupsToDelete = Backup::query()
            ->scheduled()
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->skip($retentionCount)
            ->take(PHP_INT_MAX)
            ->get();

        foreach ($backupsToDelete as $backup) {
            if (file_exists($backup->path)) {
                unlink($backup->path);
            }

            $backup->delete();
        }
    }
}
