<?php

use App\ValueObjects\Money;

test('it converts major units to minor units with half-up rounding', function () {
    expect(Money::fromMajor(150.75)->minor())->toBe(15075)
        ->and(Money::fromMajor('150.75')->minor())->toBe(15075)
        ->and(Money::fromMajor(100)->minor())->toBe(10000)
        ->and(Money::fromMajor(0.005)->minor())->toBe(1)
        ->and(Money::fromMajor(19.99)->minor())->toBe(1999)
        ->and(Money::fromMajor(0.1 + 0.2)->minor())->toBe(30);
});

test('it converts minor units back to major units', function () {
    expect(Money::fromMinor(15075)->major())->toBe(150.75)
        ->and(Money::fromMinor(0)->major())->toBe(0.0)
        ->and(Money::fromMinor(-75)->major())->toBe(-0.75);
});

test('it rejects non-numeric strings', function () {
    expect(fn () => Money::fromMajor('abc'))->toThrow(InvalidArgumentException::class);
});

test('it supports arithmetic without losing precision', function () {
    $balance = Money::fromMajor(50.00)
        ->add(Money::fromMajor(150.75))
        ->subtract(Money::fromMajor(0.75));

    expect($balance->minor())->toBe(20000)
        ->and($balance->major())->toBe(200.00)
        ->and($balance->negate()->minor())->toBe(-20000)
        ->and($balance->isNegative())->toBeFalse()
        ->and($balance->negate()->isNegative())->toBeTrue()
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and($balance->equals(Money::fromMinor(20000)))->toBeTrue();
});

test('it serializes to major units for the UI', function () {
    expect(json_encode(['total' => Money::fromMinor(15075)]))->toBe('{"total":150.75}');
});
