<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Notifications\AdminCustomEmailNotification;

class SendAdminEmailAction
{
    /**
     * Send a custom email to the given users. Returns the number of recipients notified.
     *
     * @param  array<int, int>  $userIds
     */
    public function handle(array $userIds, string $subject, string $body, ?string $fromName = null, ?string $replyTo = null): int
    {
        $users = User::whereIn('id', $userIds)->get();

        $notification = (new AdminCustomEmailNotification($subject, $body, $fromName, $replyTo))->locale(app()->getLocale());

        $users->each(fn (User $user) => $user->notify($notification));

        return $users->count();
    }
}
