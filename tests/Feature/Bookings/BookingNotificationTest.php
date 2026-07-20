<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingReminderNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner); // attaches the acting user as tenant owner
    $this->service = Product::factory()->service()->create(['duration_minutes' => 60]);
});

function bookingAt(Product $service, $startsAt, BookingStatus $status = BookingStatus::Confirmed): Booking
{
    return Booking::factory()->create([
        'service_product_id' => $service->id,
        'starts_at' => $startsAt,
        'status' => $status,
    ]);
}

test('the reminder scan notifies the merchant for a booking within 24h and stamps it', function () {
    $booking = bookingAt($this->service, now()->addHours(10));

    Artisan::call('bookings:notify-upcoming');

    Notification::assertSentTo($this->owner, BookingReminderNotification::class);
    expect($booking->fresh()->reminder_sent_at)->not->toBeNull();
});

test('bookings outside the 24h window are not reminded', function () {
    bookingAt($this->service, now()->addDays(3)); // too far out
    bookingAt($this->service, now()->subHour());   // already past

    Artisan::call('bookings:notify-upcoming');

    Notification::assertNothingSent();
});

test('cancelled and completed bookings are not reminded', function () {
    bookingAt($this->service, now()->addHours(5), BookingStatus::Cancelled);
    bookingAt($this->service, now()->addHours(5), BookingStatus::Completed);

    Artisan::call('bookings:notify-upcoming');

    Notification::assertNothingSent();
});

test('the reminder is sent exactly once across repeated scans', function () {
    bookingAt($this->service, now()->addHours(10));

    Artisan::call('bookings:notify-upcoming');
    Artisan::call('bookings:notify-upcoming');

    Notification::assertSentToTimes($this->owner, BookingReminderNotification::class, 1);
});

test('cancelling a booking notifies the merchant', function () {
    $booking = bookingAt($this->service, now()->addDays(2));

    $this->patch(route('bookings.cancel', $booking))->assertRedirect();

    Notification::assertSentTo($this->owner, BookingCancelledNotification::class);
});
