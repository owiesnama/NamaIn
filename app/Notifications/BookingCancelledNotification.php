<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the merchant (tenant owner + admins) when a booking is cancelled.
 * The slot is freed immediately for rebooking.
 *
 * TODO(B4): add an 'sms'/'whatsapp' channel once a real gateway is wired — see
 * BookingReminderNotification for the deferred-provider note.
 */
class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'booking_cancelled';
    }

    public function broadcastType(): string
    {
        return 'booking_cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Booking cancelled: :service',
            'title_params' => ['service' => $this->booking->service?->name],
            'body' => "{$this->booking->customer?->name} — {$this->booking->starts_at?->format('Y-m-d H:i')}",
            'url' => route('bookings.index', absolute: false),
            'meta' => [
                'booking_id' => $this->booking->id,
                'service' => $this->booking->service?->name,
                'customer' => $this->booking->customer?->name,
                'starts_at' => $this->booking->starts_at?->toIso8601String(),
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Booking cancelled'))
            ->line(__('A booking has been cancelled.'))
            ->line(__('Service: :service', ['service' => $this->booking->service?->name]))
            ->line(__('Customer: :customer', ['customer' => $this->booking->customer?->name]))
            ->line(__('Was scheduled for: :time', ['time' => $this->booking->starts_at?->format('Y-m-d H:i')]))
            ->action(__('View Bookings'), route('bookings.index'));
    }
}
