<?php

use App\Enums\ProductType;

test('product type has physical and service cases with string values', function () {
    expect(ProductType::Physical->value)->toBe('physical')
        ->and(ProductType::Service->value)->toBe('service')
        ->and(ProductType::cases())->toHaveCount(2);
});

test('product type resolves from its backing value', function () {
    expect(ProductType::from('physical'))->toBe(ProductType::Physical)
        ->and(ProductType::from('service'))->toBe(ProductType::Service);
});

test('product type exposes a label for each case', function () {
    expect(ProductType::Physical->label())->toBe('Physical')
        ->and(ProductType::Service->label())->toBe('Service');
});
