<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Pos\ProcessPosCheckoutAction;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\Unit;
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

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->storage->addStock($this->product, 100, 'initial_stock', actor: $this->owner);

    $this->unit = Unit::factory()->create([
        'product_id' => $this->product->id,
        'name' => 'Box',
        'conversion_factor' => 10,
    ]);

    $this->session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);
});

test('it can checkout with a custom price', function () {
    $data = collect([
        'customer_id' => null,
        'total' => 1500,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'price' => 1500, // Custom price
            ],
        ],
    ]);

    $invoice = app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier);

    expect((int) $invoice->transactions->first()->price)->toBe(1500);
    expect((int) $invoice->total)->toBe(1500);
});

test('it can checkout with a custom unit', function () {
    $data = collect([
        'customer_id' => null,
        'total' => 5000,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2, // 2 Boxes = 20 units
                'price' => 2500,
                'unit_id' => $this->unit->id,
            ],
        ],
    ]);

    $invoice = app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier);

    $transaction = $invoice->transactions->first();
    expect((int) $transaction->quantity)->toBe(2);
    expect($transaction->unit_id)->toBe($this->unit->id);
    expect((int) $transaction->base_quantity)->toBe(20);

    // Check stock deduction
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(80);
});

test('it correctly handles preflight with units', function () {
    $this->actingAs($this->cashier);

    // Requesting 11 Boxes = 110 units. We only have 100.
    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'session_id' => $this->session->id,
        'total' => 11000,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 11,
                'price' => 1000,
                'unit_id' => $this->unit->id,
            ],
        ],
        'acknowledge_transfers' => false,
    ], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('response', function ($response) {
        return $response['requires_confirmation'] === true &&
               $response['transfers_required'][0]['quantity'] == 10; // Need 10 more base units
    });
});
