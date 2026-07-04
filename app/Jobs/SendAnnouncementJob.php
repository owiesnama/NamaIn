<?php

namespace App\Jobs;

use App\Actions\Admin\ResolveAnnouncementAudienceAction;
use App\Models\Announcement;
use App\Notifications\AnnouncementNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendAnnouncementJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    public function handle(ResolveAnnouncementAudienceAction $resolveAudience): void
    {
        $recipients = 0;

        $resolveAudience->handle($this->announcement)
            ->select(['id', 'name', 'email'])
            ->chunkById(200, function ($users) use (&$recipients) {
                Notification::send($users, new AnnouncementNotification($this->announcement));
                $recipients += $users->count();
            });

        $this->announcement->update([
            'recipients_count' => $recipients,
            'sent_at' => now(),
        ]);
    }
}
