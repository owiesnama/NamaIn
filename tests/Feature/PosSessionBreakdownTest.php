<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Pos\ProcessPosCheckoutAction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);

    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'owner', 'is_active' => true]);
    $this->actingAs($this->cashier);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->storage->addStock($this->product, 100, 'initial_stock', actor: $this->cashier);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->cash = TreasuryAccount::factory()->cash()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cash Box']);
    $this->bank = TreasuryAccount::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'name' => 'Main Bank']);

    $this->session = app(OpenPosSessionAction::class)->handle($this->storage, 0, $this->cashier);

    $checkout = app(ProcessPosCheckoutAction::class);
    $checkout->handle($this->session, collect([
        'customer_id' => $this->customer->id,
        'total' => 2000,
        'payment_method' => 'cash',
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 2000]],
    ]), $this->cashier);
    $checkout->handle($this->session, collect([
        'customer_id' => $this->customer->id,
        'total' => 3000,
        'payment_method' => 'bank_transfer',
        'treasury_account_id' => $this->bank->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 3000]],
    ]), $this->cashier);
});

test('it breaks down session sales by payment method', function () {
    $byMethod = $this->session->salesByPaymentMethod()->keyBy('method');

    expect((int) $byMethod['cash']['total'])->toBe(2000)
        ->and((int) $byMethod['bank_transfer']['total'])->toBe(3000)
        ->and($byMethod['cash']['count'])->toBe(1);
});

test('it breaks down session sales by treasury account', function () {
    $byAccount = $this->session->salesByTreasuryAccount()->keyBy('account_name');

    expect((int) $byAccount['Cash Box']['total'])->toBe(2000)
        ->and((int) $byAccount['Main Bank']['total'])->toBe(3000);
});
