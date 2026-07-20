<?php

use App\Enums\BookingStatus;

test('booking status has the three v1 cases with string values', function () {
    expect(BookingStatus::Confirmed->value)->toBe('confirmed')
        ->and(BookingStatus::Cancelled->value)->toBe('cancelled')
        ->and(BookingStatus::Completed->value)->toBe('completed')
        ->and(BookingStatus::cases())->toHaveCount(3);
});

test('booking status exposes a label for each case', function () {
    expect(BookingStatus::Confirmed->label())->toBe('Confirmed')
        ->and(BookingStatus::Cancelled->label())->toBe('Cancelled')
        ->and(BookingStatus::Completed->label())->toBe('Completed');
});
