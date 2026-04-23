<?php

use App\Enums\StorageType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Storage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only pos invoices on the pos invoices page', function () {
    $tenant = app('currentTenant');
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'owner']);
    $this->actingAs($user);

    $salePoint = Storage::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => StorageType::SALE_POINT,
    ]);

    $session = PosSession::query()->create([
        'tenant_id' => $tenant->id,
        'storage_id' => $salePoint->id,
        'opened_by' => $user->id,
        'opening_float' => 1000,
    ]);

    $namedCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'is_system' => false,
    ]);

    $walkInCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Walk-in Customer',
        'is_system' => true,
    ]);

    $posInvoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'invocable_id' => $walkInCustomer->id,
        'invocable_type' => Customer::class,
        'pos_session_id' => $session->id,
    ]);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'invocable_id' => $namedCustomer->id,
        'invocable_type' => Customer::class,
        'pos_session_id' => null,
    ]);

    $response = $this->get(route('pos.invoices'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Pos/Invoices')
        ->where('invoices.data.0.id', $posInvoice->id)
        ->where('summary.total_sales', 1)
        ->where('summary.walk_in_sales', 1)
    );
});

it('flags walk-in customer as system during pos checkout', function () {
    $tenant = app('currentTenant');
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'cashier']);
    $this->actingAs($user);

    $salePoint = Storage::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => StorageType::SALE_POINT,
    ]);

    $session = PosSession::query()->create([
        'tenant_id' => $tenant->id,
        'storage_id' => $salePoint->id,
        'opened_by' => $user->id,
        'opening_float' => 1000,
    ]);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $salePoint->addStock($product, 5, 'manual_add', actor: $user);

    $response = $this->post(route('pos.checkout'), [
        'session_id' => $session->id,
        'customer_id' => null,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100,
        ]],
        'total' => 100,
    ], ['X-Inertia' => 'true']);

    $response->assertRedirect(route('pos.index'));

    $walkIn = Customer::query()->where('name', 'Walk-in Customer')->first();

    expect($walkIn)->not->toBeNull();
    expect($walkIn->is_system)->toBeTrue();
});
