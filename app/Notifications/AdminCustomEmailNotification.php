<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminCustomEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $fromName = null,
        public readonly ?string $replyTo = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->view('emails.admin-message', [
                'notifiable' => $notifiable,
                'subject' => $this->subject,
                'body' => $this->body,
            ]);

        if ($this->fromName) {
            $mail->from(config('mail.from.address'), $this->fromName);
        }

        if ($this->replyTo) {
            $mail->replyTo($this->replyTo);
        }

        return $mail;
    }
}
