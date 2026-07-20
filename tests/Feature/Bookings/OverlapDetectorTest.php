<?php

use App\Exceptions\BookingOverlapException;
use App\Models\Booking;
use App\Models\Product;
use App\Services\Bookings\OverlapDetector;
use App\ValueObjects\TimeRange;
use Carbon\Carbon;

beforeEach(function () {
    $this->detector = app(OverlapDetector::class);
    $this->service = Product::factory()->service()->create(['duration_minutes' => 60, 'allow_overlap' => false]);
});

function candidate(Product $service, string $start): Booking
{
    $booking = new Booking(['service_product_id' => $service->id, 'starts_at' => Carbon::parse($start)]);
    $booking->setRelation('service', $service);

    return $booking;
}

test('it hard-blocks a booking overlapping a confirmed booking of the same service', function () {
    Booking::factory()->create([
        'service_product_id' => $this->service->id,
        'starts_at' => Carbon::parse('2026-08-01 10:00'),
    ]);

    expect(fn () => $this->detector->assertNoConflict(candidate($this->service, '2026-08-01 10:30')))
        ->toThrow(BookingOverlapException::class);
});

test('it allows exactly back-to-back bookings', function () {
    Booking::factory()->create([
        'service_product_id' => $this->service->id,
        'starts_at' => Carbon::parse('2026-08-01 10:00'), // ends 11:00
    ]);

    $this->detector->assertNoConflict(candidate($this->service, '2026-08-01 11:00'));

    expect(true)->toBeTrue(); // no exception thrown
});

test('allow_overlap on the service silently permits a collision', function () {
    $service = Product::factory()->service()->allowOverlap()->create(['duration_minutes' => 60]);
    Booking::factory()->create([
        'service_product_id' => $service->id,
        'starts_at' => Carbon::parse('2026-08-01 10:00'),
    ]);

    $this->detector->assertNoConflict(candidate($service, '2026-08-01 10:30'));

    expect(true)->toBeTrue();
});

test('a cancelled booking frees its slot for rebooking', function () {
    Booking::factory()->cancelled()->create([
        'service_product_id' => $this->service->id,
        'starts_at' => Carbon::parse('2026-08-01 10:00'),
    ]);

    $this->detector->assertNoConflict(candidate($this->service, '2026-08-01 10:30'));

    expect(true)->toBeTrue();
});

test('a completed booking does not block overlap', function () {
    Booking::factory()->completed()->create([
        'service_product_id' => $this->service->id,
        'starts_at' => Carbon::parse('2026-08-01 10:00'),
    ]);

    $this->detector->assertNoConflict(candidate($this->service, '2026-08-01 10:30'));

    expect(true)->toBeTrue();
});

test('a booking for another service never conflicts', function () {
    $other = Product::factory()->service()->create(['duration_minutes' => 60]);
    Booking::factory()->create([
        'service_product_id' => $other->id,
        'starts_at' => Carbon::parse('2026-08-01 10:00'),
    ]);

    $this->detector->assertNoConflict(candidate($this->service, '2026-08-01 10:30'));

    expect(true)->toBeTrue();
});

test('editing a booking excludes itself from its own overlap check', function () {
    $booking = Booking::factory()->create([
        'service_product_id' => $this->service->id,
        'starts_at' => Carbon::parse('2026-08-01 10:00'),
    ]);

    $this->detector->assertNoConflict($booking->load('service'), ignoreId: $booking->id);

    expect(true)->toBeTrue();
});

test('an empty calendar passes', function () {
    $this->detector->assertNoConflict(candidate($this->service, '2026-08-01 10:30'));

    expect(true)->toBeTrue();
});

test('the SQL predicate matches the in-memory TimeRange primitive', function () {
    // Fixtures at varied offsets around a candidate [10:00, 11:00).
    $offsets = ['2026-08-01 08:30', '2026-08-01 09:30', '2026-08-01 10:30', '2026-08-01 11:00', '2026-08-01 11:30'];
    foreach ($offsets as $start) {
        Booking::factory()->create([
            'service_product_id' => $this->service->id,
            'starts_at' => Carbon::parse($start),
        ]);
    }

    $candidate = candidate($this->service, '2026-08-01 10:00'); // ends 11:00
    $candidateRange = new TimeRange($candidate->starts_at, $candidate->deriveEndsAt());

    $expectedIds = Booking::all()->filter(fn (Booking $b) => $candidateRange->overlaps(new TimeRange($b->starts_at, $b->ends_at)))
        ->pluck('id')->sort()->values()->all();

    $actualIds = $this->detector->conflicts($candidate)->pluck('id')->sort()->values()->all();

    expect($actualIds)->toBe($expectedIds)->not->toBeEmpty();
});
