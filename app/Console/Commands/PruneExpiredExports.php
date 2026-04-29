<?php

namespace App\Console\Commands;

use App\Models\ExportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExpiredExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired export files and log rows older than 90 days';

    public function handle(): int
    {
        $expired = ExportLog::withoutGlobalScopes()
            ->where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays(90))
            ->get();

        $deleted = 0;

        foreach ($expired as $export) {
            if ($export->path && Storage::exists($export->path)) {
                Storage::delete($export->path);
            }

            $export->delete();
            $deleted++;
        }

        $this->info("Pruned {$deleted} expired export(s).");

        return self::SUCCESS;
    }
}
