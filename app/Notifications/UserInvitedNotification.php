<?php

namespace App\Notifications;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly UserInvitation $invitation) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->invitation->tenant->name;
        $inviterName = $this->invitation->inviter->name;
        $roleName = $this->invitation->role->name;
        $acceptUrl = route('invitations.accept', $this->invitation->token);

        return (new MailMessage)
            ->subject(__(':inviter invited you to join :tenant', ['inviter' => $inviterName, 'tenant' => $tenantName]))
            ->greeting(__('You have been invited!'))
            ->line(__(':inviter has invited you to join :tenant as :role.', [
                'inviter' => $inviterName,
                'tenant' => $tenantName,
                'role' => $roleName,
            ]))
            ->action(__('Accept Invitation'), $acceptUrl)
            ->line(__('This invitation expires in 7 days.'))
            ->line(__('If you did not expect this invitation, you may ignore this email.'));
    }
}
