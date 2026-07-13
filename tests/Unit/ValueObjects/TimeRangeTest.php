<?php

use App\ValueObjects\TimeRange;
use Carbon\Carbon;

function timeRange(string $start, string $end): TimeRange
{
    return new TimeRange(Carbon::parse($start), Carbon::parse($end));
}

test('overlapping ranges are detected', function () {
    expect(timeRange('2026-08-01 10:00', '2026-08-01 11:00')
        ->overlaps(timeRange('2026-08-01 10:30', '2026-08-01 11:30')))->toBeTrue();
});

test('a fully contained range overlaps', function () {
    expect(timeRange('2026-08-01 10:00', '2026-08-01 12:00')
        ->overlaps(timeRange('2026-08-01 10:30', '2026-08-01 11:00')))->toBeTrue();
});

test('identical ranges overlap', function () {
    expect(timeRange('2026-08-01 10:00', '2026-08-01 11:00')
        ->overlaps(timeRange('2026-08-01 10:00', '2026-08-01 11:00')))->toBeTrue();
});

test('exactly abutting ranges do NOT overlap (half-open)', function () {
    expect(timeRange('2026-08-01 10:00', '2026-08-01 11:00')
        ->overlaps(timeRange('2026-08-01 11:00', '2026-08-01 12:00')))->toBeFalse();
});

test('disjoint ranges do not overlap', function () {
    expect(timeRange('2026-08-01 10:00', '2026-08-01 11:00')
        ->overlaps(timeRange('2026-08-01 11:30', '2026-08-01 12:00')))->toBeFalse();
});

test('same-instant zero-duration ranges do not overlap', function () {
    expect(timeRange('2026-08-01 10:00', '2026-08-01 10:00')
        ->overlaps(timeRange('2026-08-01 10:00', '2026-08-01 10:00')))->toBeFalse();
});
