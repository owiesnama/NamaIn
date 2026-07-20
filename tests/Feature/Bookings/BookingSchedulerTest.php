<?php

use App\Exceptions\BookingOverlapException;
use App\Models\Booking;
use App\Models\Product;
use App\Services\Bookings\BookingScheduler;
use Carbon\Carbon;

beforeEach(function () {
    $this->scheduler = app(BookingScheduler::class);
});

function schedulerCandidate(Product $service, string $start): Booking
{
    $booking = new Booking(['service_product_id' => $service->id, 'starts_at' => Carbon::parse($start)]);
    $booking->setRelation('service', $service);

    return $booking;
}

test('it throws on overlap before considering the buffer', function () {
    $service = Product::factory()->service()->onSite(30)->create(['duration_minutes' => 60]);
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 10:00')]);

    expect(fn () => $this->scheduler->assertBookable(schedulerCandidate($service, '2026-08-01 10:30')))
        ->toThrow(BookingOverlapException::class);
});

test('a clean placement returns no warnings', function () {
    $service = Product::factory()->service()->create(['duration_minutes' => 60]);

    expect($this->scheduler->assertBookable(schedulerCandidate($service, '2026-08-01 10:00')))->toBe([]);
});

test('a placement that clears overlap but breaches the buffer returns warnings without throwing', function () {
    $service = Product::factory()->service()->onSite(30)->create(['duration_minutes' => 60]);
    // Neighbour ends 10:00; candidate at 10:20 does not overlap but is within the 30-min buffer.
    Booking::factory()->create(['service_product_id' => $service->id, 'starts_at' => Carbon::parse('2026-08-01 09:00')]);

    $warnings = $this->scheduler->assertBookable(schedulerCandidate($service, '2026-08-01 10:20'));

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]->direction)->toBe('before');
});
