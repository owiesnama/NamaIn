<?php

namespace App\Notifications;

use App\Models\Cheque;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChequeDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Cheque $cheque) {}

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
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
            ->line("Amount: {$this->cheque->amount_formatted}")
            ->line("Due Date: {$this->cheque->due->format('d-m-Y')}")
            ->line('Current Status: '.__(str($this->cheque->status->value)->title()->replace('_', ' ')))
            ->action('View Cheque', route('cheques.index', ['search' => $this->cheque->reference_number]))
            ->line('Thank you for using our application!');
    }
}
