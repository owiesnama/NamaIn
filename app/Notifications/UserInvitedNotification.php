<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $inviterName,
        private readonly string $roleName,
        private readonly string $acceptUrl,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':inviter invited you to join :tenant', ['inviter' => $this->inviterName, 'tenant' => $this->tenantName]))
            ->greeting(__('You have been invited!'))
            ->line(__(':inviter has invited you to join :tenant as :role.', [
                'inviter' => $this->inviterName,
                'tenant' => $this->tenantName,
                'role' => $this->roleName,
            ]))
            ->action(__('Accept Invitation'), $this->acceptUrl)
            ->line(__('This invitation expires in 7 days.'))
            ->line(__('If you did not expect this invitation, you may ignore this email.'));
    }
}
