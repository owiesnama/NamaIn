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
            ->subject(__(':inviter invited you to join :tenant', [
                'inviter' => $this->inviterName,
                'tenant' => $this->tenantName,
            ]))
            ->view('emails.invitation', [
                'tenantName' => $this->tenantName,
                'inviterName' => $this->inviterName,
                'roleName' => $this->roleName,
                'acceptUrl' => $this->acceptUrl,
            ]);
    }
}
