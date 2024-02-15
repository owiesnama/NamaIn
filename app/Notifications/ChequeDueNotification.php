<?php

namespace App\Notifications;

use App\Channels\MazinHost\Channel;
use App\Models\Cheque;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

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
        return [Channel::class];
    }

    public function toMazinHost(){
        
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
