<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;

class InvalidateAllUserSessions
{
    public function handle(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        DB::table('sessions')
            ->where('user_id', $event->user->getAuthIdentifier())
            ->delete();
    }
}
