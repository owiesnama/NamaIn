<?php

namespace App\Notifications;

use App\Models\Cheque;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChequeDueNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(public Cheque $cheque) {}

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
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $direction = $this->cheque->isReceivable() ? 'Receivable' : 'Payable';

        return (new MailMessage)
            ->subject("Cheque Due Notification: #{$this->cheque->reference_number}")
            ->line('This is a reminder that a cheque is due.')
            ->line("Reference Number: #{$this->cheque->reference_number}")
            ->line("Direction: {$direction}")
            ->line("Payee: {$this->cheque->payee->name}")
            ->line("Amount: {$this->cheque->amount_formated}")
            ->line("Due Date: {$this->cheque->due->format('d-m-Y')}")
            ->line('Current Status: '.__(str($this->cheque->status->value)->title()->replace('_', ' ')))
            ->action('View Cheque', route('cheques.index', ['search' => $this->cheque->reference_number]))
            ->line('Thank you for using our application!');
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
