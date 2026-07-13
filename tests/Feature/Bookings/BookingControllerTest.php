<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceAddon;

beforeEach(function () {
    $this->signIn();
    $this->service = Product::factory()->service()->create(['duration_minutes' => 60, 'price' => 100]);
    $this->customer = Customer::factory()->create();
});

test('it creates a booking with snapshotted add-ons and a stored total', function () {
    $addon = ServiceAddon::factory()->create(['product_id' => $this->service->id, 'price_delta' => 50]);

    $this->post(route('bookings.store'), [
        'service_product_id' => $this->service->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-01 10:00:00',
        'addons' => [$addon->id],
    ])->assertRedirect(route('bookings.index'));

    $booking = Booking::firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->total)->toBe(150.0)
        ->and($booking->addons)->toHaveCount(1);
});

test('a non-bookable service is rejected', function () {
    $walkIn = Product::factory()->service()->create(['requires_booking' => false]);

    $this->post(route('bookings.store'), [
        'service_product_id' => $walkIn->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-01 10:00:00',
    ])->assertSessionHasErrors('service_product_id');
});

test('an on-site booking requires an address', function () {
    $onSite = Product::factory()->service()->onSite(30)->create(['duration_minutes' => 60]);

    $this->post(route('bookings.store'), [
        'service_product_id' => $onSite->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-01 10:00:00',
    ])->assertSessionHasErrors('address');
});

test('an overlapping booking is hard-blocked with a 422 and nothing persists', function () {
    Booking::factory()->create([
        'service_product_id' => $this->service->id,
        'starts_at' => '2026-08-01 10:00:00', // ends 11:00
    ]);

    $this->post(route('bookings.store'), [
        'service_product_id' => $this->service->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-01 10:30:00',
    ])->assertSessionHasErrors('starts_at');

    expect(Booking::count())->toBe(1);
});

test('allow_overlap services permit a colliding booking', function () {
    $service = Product::factory()->service()->allowOverlap()->create(['duration_minutes' => 60]);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => '2026-08-01 10:00:00']);

    $this->post(route('bookings.store'), [
        'service_product_id' => $service->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-01 10:30:00',
    ])->assertRedirect(route('bookings.index'));

    expect(Booking::count())->toBe(2);
});

test('a travel-buffer breach soft-warns and holds until acknowledged', function () {
    $service = Product::factory()->service()->onSite(30)->create(['duration_minutes' => 60]);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => '2026-08-01 09:00:00']); // ends 10:00

    $payload = [
        'service_product_id' => $service->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-01 10:20:00', // gap 20 < 30, no overlap
        'address' => 'حي الرياض',
    ];

    // First submit: warned, not saved.
    $this->post(route('bookings.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('travel_buffer_warnings');
    expect(Booking::count())->toBe(1);

    // Acknowledged submit: saved.
    $this->post(route('bookings.store'), $payload + ['acknowledge_buffer' => true])
        ->assertRedirect(route('bookings.index'));
    expect(Booking::count())->toBe(2);
});

test('editing a booking excludes itself from the overlap check and re-snapshots add-ons', function () {
    $booking = Booking::factory()->create([
        'service_product_id' => $this->service->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-01 10:00:00',
        'base_price' => 100,
    ]);
    $addon = ServiceAddon::factory()->create(['product_id' => $this->service->id, 'price_delta' => 25]);

    $this->put(route('bookings.update', $booking), [
        'service_product_id' => $this->service->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-02 09:00:00',
        'addons' => [$addon->id],
    ])->assertRedirect(route('bookings.index'));

    $booking->refresh()->load('addons');

    expect($booking->starts_at->format('Y-m-d H:i'))->toBe('2026-08-02 09:00')
        ->and($booking->addons)->toHaveCount(1)
        ->and($booking->total)->toBe(125.0);
});

test('cancelling a booking frees its slot', function () {
    $booking = Booking::factory()->create(['service_product_id' => $this->service->id, 'starts_at' => '2026-08-01 10:00:00']);

    $this->patch(route('bookings.cancel', $booking))->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);

    // The slot is now rebookable.
    $this->post(route('bookings.store'), [
        'service_product_id' => $this->service->id,
        'customer_id' => $this->customer->id,
        'starts_at' => '2026-08-01 10:00:00',
    ])->assertRedirect(route('bookings.index'));

    expect(Booking::confirmed()->count())->toBe(1);
});

// NOTE: the Bookings/Index render + calendar payload assertions live in the B3
// UI commit, once resources/js/Pages/Bookings/Index.vue exists in the Vite
// manifest. B3a covers the controller's write paths and engine wiring above.
