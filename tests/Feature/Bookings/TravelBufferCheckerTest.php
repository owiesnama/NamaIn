<?php

use App\Models\Booking;
use App\Models\Product;
use App\Services\Bookings\TravelBufferChecker;
use Carbon\Carbon;

beforeEach(function () {
    $this->checker = app(TravelBufferChecker::class);
});

function onSiteService(int $buffer = 30, int $duration = 60): Product
{
    return Product::factory()->service()->onSite($buffer)->create(['duration_minutes' => $duration]);
}

function bufferCandidate(Product $service, string $start): Booking
{
    $booking = new Booking(['service_product_id' => $service->id, 'starts_at' => Carbon::parse($start)]);
    $booking->setRelation('service', $service);

    return $booking;
}

test('a preceding booking closer than the buffer produces a before-warning', function () {
    $service = onSiteService(buffer: 30);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 09:00')]); // ends 10:00

    $warnings = $this->checker->warningsFor(bufferCandidate($service, '2026-08-01 10:20')); // gap 20 < 30

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]->direction)->toBe('before')
        ->and($warnings[0]->gapMinutes)->toBe(20)
        ->and($warnings[0]->requiredBufferMinutes)->toBe(30);
});

test('a gap exactly equal to the buffer yields no warning', function () {
    $service = onSiteService(buffer: 30);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 09:00')]); // ends 10:00

    $warnings = $this->checker->warningsFor(bufferCandidate($service, '2026-08-01 10:30')); // gap 30

    expect($warnings)->toBeEmpty();
});

test('a gap larger than the buffer yields no warning', function () {
    $service = onSiteService(buffer: 30);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 09:00')]);

    expect($this->checker->warningsFor(bufferCandidate($service, '2026-08-01 10:45')))->toBeEmpty();
});

test('a following booking closer than the buffer produces an after-warning', function () {
    $service = onSiteService(buffer: 30);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 11:20')]);

    $warnings = $this->checker->warningsFor(bufferCandidate($service, '2026-08-01 10:00')); // ends 11:00, gap 20

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]->direction)->toBe('after')
        ->and($warnings[0]->gapMinutes)->toBe(20);
});

test('a booking wedged between two close neighbours warns twice', function () {
    $service = onSiteService(buffer: 30);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 08:50')]); // ends 09:50
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 11:15')]);

    $warnings = $this->checker->warningsFor(bufferCandidate($service, '2026-08-01 10:00')); // ends 11:00

    expect($warnings)->toHaveCount(2)
        ->and(collect($warnings)->pluck('direction')->all())->toEqualCanonicalizing(['before', 'after']);
});

test('non-on-site services never warn', function () {
    $service = Product::factory()->service()->create(['duration_minutes' => 60]); // on_site false
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 09:59')]);

    expect($this->checker->warningsFor(bufferCandidate($service, '2026-08-01 10:00')))->toBeEmpty();
});

test('a zero or null buffer yields no warning', function () {
    $service = Product::factory()->service()->create(['duration_minutes' => 60, 'on_site' => true, 'travel_buffer_minutes' => 0]);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 09:59')]);

    expect($this->checker->warningsFor(bufferCandidate($service, '2026-08-01 10:00')))->toBeEmpty();
});

test('a cancelled neighbour never triggers a warning', function () {
    $service = onSiteService(buffer: 30);
    Booking::factory()->cancelled()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 09:50')]);

    expect($this->checker->warningsFor(bufferCandidate($service, '2026-08-01 10:00')))->toBeEmpty();
});

test('the edited booking excludes itself from the buffer check', function () {
    $service = onSiteService(buffer: 30);
    $booking = Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 10:00')]);

    expect($this->checker->warningsFor($booking->load('service'), ignoreId: $booking->id))->toBeEmpty();
});
