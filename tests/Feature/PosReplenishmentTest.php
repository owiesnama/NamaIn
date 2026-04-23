<?php

use App\Actions\Pos\FindReplenishmentSourceAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Enums\StorageType;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);

    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner']);

    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier']);

    // Create Sale Point
    $this->salePoint = Storage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => StorageType::SALE_POINT,
    ]);

    // Create Warehouse
    $this->warehouse = Storage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => StorageType::WAREHOUSE,
    ]);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    // Put stock in warehouse only
    $this->warehouse->addStock($this->product, 50, 'initial_stock', actor: $this->owner);

    $this->session = app(OpenPosSessionAction::class)->execute($this->salePoint, 5000, $this->cashier);
});

test('it finds replenishment source correctly', function () {
    $action = app(FindReplenishmentSourceAction::class);
    $source = $action->handle($this->product, 10);

    expect($source)->not->toBeNull();
    expect($source->warehouse->id)->toBe($this->warehouse->id);
    expect($source->availableQuantity)->toBe(50);
});

test('preflight returns transfers_required when local stock is insufficient', function () {
    $this->actingAs($this->cashier);

    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'session_id' => $this->session->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'price' => 100]],
        'total' => 500,
        'acknowledge_transfers' => false,
    ], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('response', function ($response) {
        return $response['requires_confirmation'] === true &&
               count($response['transfers_required']) === 1;
    });
});

test('preflight returns unavailable_products when stock is nowhere sufficient', function () {
    $this->actingAs($this->cashier);

    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'session_id' => $this->session->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 100, 'price' => 100]],
        'total' => 10000,
        'acknowledge_transfers' => false,
    ], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('response', function ($response) {
        return $response['requires_confirmation'] === true &&
               $response['success'] === false &&
               count($response['unavailable_products']) === 1;
    });
});

test('checkout with acknowledge_transfers=true executes transfer and checkout atomically', function () {
    $this->actingAs($this->cashier);

    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'session_id' => $this->session->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'price' => 100]],
        'total' => 500,
        'acknowledge_transfers' => true,
    ], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect(route('pos.index'));
    $response->assertSessionHas('success');

    // Check stock
    expect($this->warehouse->fresh()->quantityOf($this->product))->toBe(45);
    expect($this->salePoint->fresh()->quantityOf($this->product))->toBe(0); // 5 transferred in, 5 deducted for sale

    // Check ledger/transfers
    $this->assertDatabaseHas('stock_transfers', [
        'from_storage_id' => $this->warehouse->id,
        'to_storage_id' => $this->salePoint->id,
    ]);
});

test('it only transfers the shortfall', function () {
    // Add some stock to sale point
    $this->salePoint->addStock($this->product, 3, 'partial_stock', actor: $this->owner);

    $this->actingAs($this->cashier);

    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'session_id' => $this->session->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'price' => 100]],
        'total' => 500,
        'acknowledge_transfers' => true,
    ], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect(route('pos.index'));

    // Warehouse should lose 2 (5 needed - 3 available)
    expect($this->warehouse->fresh()->quantityOf($this->product))->toBe(48);
    // Sale point should be 0
    expect($this->salePoint->fresh()->quantityOf($this->product))->toBe(0);
});
