<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->announcement->send_email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function databaseType(object $notifiable): string
    {
        return 'announcement';
    }

    public function broadcastType(): string
    {
        return 'announcement';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->announcement->subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->lines(explode("\n", $this->announcement->body));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->announcement->subject,
            'body' => $this->announcement->body,
            'url' => '/notifications',
            'meta' => ['announcement_id' => $this->announcement->id],
        ];
    }
}
