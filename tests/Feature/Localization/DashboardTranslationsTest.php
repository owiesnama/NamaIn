<?php

declare(strict_types=1);

beforeEach(function () {
    app()->setLocale('ar');
});

it('translates the negative-stock report description in Arabic', function () {
    $key = 'Products below zero, how long, and the movements that drove them there.';

    expect(__($key))
        ->not->toBe($key)
        ->toContain('المخزون السالب');
});

it('translates the team role slugs shown on the members table in Arabic', function () {
    expect(__('owner'))->toBe('مالك')
        ->and(__('manager'))->toBe('مدير')
        ->and(__('cashier'))->toBe('أمين الصندوق')
        ->and(__('staff'))->toBe('موظف');
});
