<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserCredentialsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $temporaryPassword,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your account has been created on :tenant', ['tenant' => $this->tenantName]))
            ->greeting(__('Welcome, :name!', ['name' => $notifiable->name]))
            ->line(__('An account has been created for you on :tenant.', ['tenant' => $this->tenantName]))
            ->line(__('Here are your login credentials:'))
            ->line(__('**Email:** :email', ['email' => $notifiable->email]))
            ->line(__('**Temporary Password:** :password', ['password' => $this->temporaryPassword]))
            ->line(__('You will be required to set a new password on your first login.'))
            ->action(__('Login Now'), url('/login'))
            ->line(__('If you did not expect this email, please ignore it.'));
    }
}
