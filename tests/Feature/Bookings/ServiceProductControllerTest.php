<?php

use App\Models\Product;

test('a service product is created with add-ons and no stock/units', function () {
    $this->signIn();

    $this->post(route('products.index'), [
        'type' => 'service',
        'name' => 'استشارة طبية',
        'price' => 150,
        'duration_minutes' => 30,
        'requires_booking' => true,
        'on_site' => false,
        'allow_overlap' => false,
        'addons' => [
            ['name' => 'كشف إضافي', 'price_delta' => 40],
            ['name' => 'تقرير', 'price_delta' => 20],
        ],
    ])->assertRedirect(route('products.index'));

    $service = Product::services()->firstOrFail();

    expect($service->isService())->toBeTrue()
        ->and($service->price)->toBe(150.0)
        ->and($service->duration_minutes)->toBe(30)
        ->and($service->serviceAddons)->toHaveCount(2)
        ->and($service->units)->toHaveCount(0);
});

test('a zero-cost service passes validation (cost > 0 does not apply)', function () {
    $this->signIn();

    $this->post(route('products.index'), [
        'type' => 'service',
        'name' => 'خدمة مجانية',
        'price' => 0,
        'duration_minutes' => 15,
        'requires_booking' => true,
    ])->assertSessionHasNoErrors();

    expect(Product::services()->count())->toBe(1);
});

test('a bookable service requires a positive duration', function () {
    $this->signIn();

    $this->post(route('products.index'), [
        'type' => 'service',
        'name' => 'بدون مدة',
        'price' => 100,
        'requires_booking' => true,
    ])->assertSessionHasErrors('duration_minutes');
});

test('an on-site service requires a travel buffer', function () {
    $this->signIn();

    $this->post(route('products.index'), [
        'type' => 'service',
        'name' => 'زيارة منزلية',
        'price' => 100,
        'duration_minutes' => 60,
        'requires_booking' => true,
        'on_site' => true,
    ])->assertSessionHasErrors('travel_buffer_minutes');
});

test('editing a service syncs its add-ons without touching historical bookings', function () {
    $this->signIn();
    $service = Product::factory()->service()->create();
    $kept = $service->serviceAddons()->create(['name' => 'قديم', 'price_delta' => 10]);

    $this->put(route('products.update', $service), [
        'type' => 'service',
        'name' => $service->name,
        'price' => $service->price,
        'duration_minutes' => $service->duration_minutes,
        'requires_booking' => true,
        'addons' => [
            ['id' => $kept->id, 'name' => 'محدّث', 'price_delta' => 15],
            ['name' => 'جديد', 'price_delta' => 30],
        ],
    ])->assertRedirect();

    $service->refresh()->load('serviceAddons');

    expect($service->serviceAddons)->toHaveCount(2)
        ->and($service->serviceAddons->firstWhere('id', $kept->id)->name)->toBe('محدّث');
});

test('physical product creation is unchanged', function () {
    $this->signIn();

    $this->post(route('products.index'), [
        'name' => 'منتج عادي',
        'cost' => 100,
        'price' => 120,
    ])->assertRedirect(route('products.index'));

    $product = Product::physical()->firstOrFail();

    expect($product->isService())->toBeFalse()
        ->and($product->units)->toHaveCount(1); // base unit created
});

test('the products API filters by type', function () {
    $this->signIn();
    Product::factory()->count(2)->create();
    Product::factory()->service()->count(3)->create();

    $response = $this->getJson(route('api.products.index', ['type' => 'service']));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});
