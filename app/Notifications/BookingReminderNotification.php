<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fixed 24-hour-before reminder for a confirmed booking, sent to the merchant
 * (tenant owner + admins). Not configurable in v1.
 *
 * TODO(B4): add an 'sms'/'whatsapp' channel here once a real gateway is wired.
 * No working provider exists yet — the installed laravel-notification-channels/
 * twilio package is unused and config/services.php has a dangling `mazin_host`
 * SMS stub (MAZIN_HOST_SMS_API_KEY / MAZIN_HOST_SENDER_ID, no keys in
 * .env.example). Wiring it needs a channel class + `routeNotificationForSms()`
 * on the User notifiable + env keys. Deferred to a future initiative.
 */
class BookingReminderNotification extends Notification implements ShouldQueue
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
        return 'booking_reminder';
    }

    public function broadcastType(): string
    {
        return 'booking_reminder';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Upcoming booking: :service',
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
            ->subject(__('Upcoming booking reminder'))
            ->line(__('This is a reminder for an upcoming booking.'))
            ->line(__('Service: :service', ['service' => $this->booking->service?->name]))
            ->line(__('Customer: :customer', ['customer' => $this->booking->customer?->name]))
            ->line(__('Starts: :time', ['time' => $this->booking->starts_at?->format('Y-m-d H:i')]))
            ->action(__('View Bookings'), route('bookings.index'))
            ->line(__('Thank you for using our application!'));
    }
}
