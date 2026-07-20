<?php

use App\Actions\Bookings\CreateBookingAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceAddon;
use Carbon\Carbon;

beforeEach(function () {
    $this->action = app(CreateBookingAction::class);
});

test('it snapshots the base price and selected add-on deltas into the stored total', function () {
    $service = Product::factory()->service()->create(['price' => 100, 'duration_minutes' => 45]);
    $customer = Customer::factory()->create();
    $addonA = ServiceAddon::factory()->create(['product_id' => $service->id, 'price_delta' => 30]);
    $addonB = ServiceAddon::factory()->create(['product_id' => $service->id, 'price_delta' => 20]);

    $booking = $this->action->handle(
        $service,
        $customer,
        Carbon::parse('2026-08-01 10:00:00'),
        [$addonA->id, $addonB->id],
    );

    expect($booking->base_price)->toBe(100.0)
        ->and($booking->total)->toBe(150.0)
        ->and($booking->addons)->toHaveCount(2)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->ends_at->equalTo(Carbon::parse('2026-08-01 10:45:00')))->toBeTrue();
});

test('selecting no add-ons yields a total equal to the base price', function () {
    $service = Product::factory()->service()->create(['price' => 80]);
    $customer = Customer::factory()->create();

    $booking = $this->action->handle($service, $customer, Carbon::parse('2026-08-01 10:00:00'));

    expect($booking->addons)->toHaveCount(0)
        ->and($booking->total)->toBe(80.0);
});

test('re-pricing the service or its add-ons never mutates a historical booking', function () {
    $service = Product::factory()->service()->create(['price' => 100]);
    $customer = Customer::factory()->create();
    $addon = ServiceAddon::factory()->create(['product_id' => $service->id, 'price_delta' => 50, 'name' => 'كشف إضافي']);

    $booking = $this->action->handle($service, $customer, Carbon::parse('2026-08-01 10:00:00'), [$addon->id]);

    // Mutate the sources after the fact.
    $service->update(['price' => 999]);
    $addon->update(['price_delta' => 999, 'name' => 'تغيير']);

    $booking->refresh()->load('addons');

    expect($booking->base_price)->toBe(100.0)
        ->and($booking->total)->toBe(150.0)
        ->and($booking->addons->first()->price_delta)->toBe(50.0)
        ->and($booking->addons->first()->name)->toBe('كشف إضافي');
});

test('deleting a source add-on preserves the booking snapshot', function () {
    $service = Product::factory()->service()->create(['price' => 100]);
    $customer = Customer::factory()->create();
    $addon = ServiceAddon::factory()->create(['product_id' => $service->id, 'price_delta' => 40, 'name' => 'متابعة']);

    $booking = $this->action->handle($service, $customer, Carbon::parse('2026-08-01 10:00:00'), [$addon->id]);

    $addon->forceDelete();

    $line = $booking->refresh()->addons->first();

    expect($line->service_addon_id)->toBeNull()
        ->and($line->name)->toBe('متابعة')
        ->and($line->price_delta)->toBe(40.0)
        ->and($booking->total)->toBe(140.0);
});

test('an add-on from another service is rejected', function () {
    $service = Product::factory()->service()->create(['price' => 100]);
    $otherService = Product::factory()->service()->create();
    $customer = Customer::factory()->create();
    $foreignAddon = ServiceAddon::factory()->create(['product_id' => $otherService->id]);

    expect(fn () => $this->action->handle(
        $service,
        $customer,
        Carbon::parse('2026-08-01 10:00:00'),
        [$foreignAddon->id],
    ))->toThrow(InvalidArgumentException::class);

    expect(Booking::count())->toBe(0);
});
