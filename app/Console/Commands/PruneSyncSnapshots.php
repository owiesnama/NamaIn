<?php

namespace App\Console\Commands;

use App\Models\SyncSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneSyncSnapshots extends Command
{
    protected $signature = 'sync:prune-snapshots';

    protected $description = 'Delete expired device bootstrap snapshots and their files';

    public function handle(): int
    {
        $expired = SyncSnapshot::withoutGlobalScopes()
            ->where('expires_at', '<', now())
            ->get();

        $deleted = 0;

        foreach ($expired as $snapshot) {
            Storage::disk('local')->deleteDirectory($snapshot->directory());

            $snapshot->delete();
            $deleted++;
        }

        $this->info("Pruned {$deleted} expired snapshot(s).");

        return self::SUCCESS;
    }
}
