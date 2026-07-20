<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;

test('a booking persists a derived ends_at from the service duration', function () {
    $service = Product::factory()->service()->create(['duration_minutes' => 60]);
    $start = Carbon::parse('2026-08-01 10:00:00');

    $booking = Booking::factory()->create([
        'service_product_id' => $service->id,
        'starts_at' => $start,
    ]);

    expect($booking->refresh()->ends_at->equalTo($start->copy()->addMinutes(60)))->toBeTrue();
});

test('moving the start recomputes the persisted end', function () {
    $service = Product::factory()->service()->create(['duration_minutes' => 30]);
    $booking = Booking::factory()->create([
        'service_product_id' => $service->id,
        'starts_at' => Carbon::parse('2026-08-01 09:00:00'),
    ]);

    $newStart = Carbon::parse('2026-08-02 14:00:00');
    $booking->update(['starts_at' => $newStart]);

    expect($booking->refresh()->ends_at->equalTo($newStart->copy()->addMinutes(30)))->toBeTrue();
});

test('a null service duration yields ends_at equal to starts_at', function () {
    $service = Product::factory()->service()->create(['duration_minutes' => null]);
    $start = Carbon::parse('2026-08-01 10:00:00');

    $booking = Booking::factory()->create([
        'service_product_id' => $service->id,
        'starts_at' => $start,
    ]);

    expect($booking->refresh()->ends_at->equalTo($start))->toBeTrue();
});

test('a booking casts its relationships, status and money', function () {
    $booking = Booking::factory()->create(['base_price' => 120]);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->base_price)->toBe(120.0)
        ->and($booking->service)->toBeInstanceOf(Product::class)
        ->and($booking->customer)->toBeInstanceOf(Customer::class);
});

test('the confirmed and status scopes filter by status', function () {
    Booking::factory()->count(2)->create();
    Booking::factory()->cancelled()->create();
    Booking::factory()->completed()->create();

    expect(Booking::confirmed()->count())->toBe(2)
        ->and(Booking::status(BookingStatus::Cancelled)->count())->toBe(1)
        ->and(Booking::status(BookingStatus::Completed)->count())->toBe(1);
});

test('a booking is tenant scoped and soft-deletable', function () {
    $booking = Booking::factory()->create();

    expect($booking->tenant_id)->not->toBeNull();

    $booking->delete();

    expect(Booking::whereKey($booking->id)->exists())->toBeFalse()
        ->and(Booking::withTrashed()->whereKey($booking->id)->exists())->toBeTrue();
});
