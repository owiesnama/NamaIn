<?php

namespace App\Notifications;

use App\Models\Cheque;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;
use Illuminate\Notifications\Messages\MailMessage;

class ChequeDueNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(public Cheque $cheque)
    {
    }

    public function messageContent($notifiable)
    {
        return "Dear {$notifiable->name},\nA cheque ({$this->getChequeType()}) related to payee {$this->cheque->payee->name} is due on {$this->cheque->due->format('d-m-Y')}.\nPlease take necessary actions for timely payment.\nThank you.\nBest regards";
    }

    public function getChequeType()
    {
        return match ($this->cheque->type) {
            0 => 'Debit',
            1 => 'Credit',
            default => 'debit'
        };
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [TwilioChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->line($this->messageContent($notifiable))
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    public function toTwilio($notifiable)
    {
        return (new TwilioSmsMessage())
            ->content($this->messageContent($notifiable));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
