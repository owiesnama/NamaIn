<?php

use App\Actions\Reconciliation\RaiseReconciliationItem;
use App\Enums\ReconciliationType;
use App\Enums\StorageType;
use App\Models\CreditBreachFlag;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OversellReconciliation;
use App\Models\Product;
use App\Models\ReconciliationItem;
use App\Models\Role;
use App\Models\SessionVariance;
use App\Models\Storage;
use App\Models\User;

/**
 * Build reconciliation subjects directly (no HTTP push) so the web guard stays
 * clean for the resolving owner. The push path itself is covered by PR-1/PR-2.
 */
function rrOwner(): User
{
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);
    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'owner')->first();
    $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    return $owner;
}

function rrStorage(): Storage
{
    return Storage::create(['name' => 'Front Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
}

function rrRaise(object $subject, ReconciliationType $type): ReconciliationItem
{
    return app(RaiseReconciliationItem::class)->for(subject: $subject, type: $type);
}

it('denies the inbox to users without reconciliation.view', function () {
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);
    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'cashier')->first();
    $cashier = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);

    $this->actingAs($cashier)->get(route('reconciliation.index'))->assertForbidden();
});

it('shows the inbox to an owner', function () {
    $this->actingAs(rrOwner())->get(route('reconciliation.index'))->assertSuccessful();
});

it('resolves an oversell by adjusting stock through the ledger', function () {
    $owner = rrOwner();
    $storage = rrStorage();
    $product = Product::create(['name' => 'Cola', 'cost' => 5, 'price' => 10, 'currency' => 'SDG']);
    $storage->setStockTo($product, -3, 'seed');
    $invoice = Invoice::factory()->create(['invocable_id' => $owner->id, 'invocable_type' => User::class]);

    $oversell = OversellReconciliation::create([
        'tenant_id' => $storage->tenant_id,
        'storage_id' => $storage->id,
        'product_id' => $product->id,
        'invoice_id' => $invoice->id,
        'oversold_qty' => 3,
        'on_hand_before' => 0,
        'occurred_at' => now(),
    ]);
    $item = rrRaise($oversell, ReconciliationType::Oversell);

    $this->actingAs($owner)
        ->post(route('reconciliation.resolve', $item->id), ['resolution' => 'adjust', 'counted_qty' => 10, 'note' => 'counted'])
        ->assertRedirect(route('reconciliation.index'));

    expect($item->fresh()->status)->toBe('resolved');
    expect($item->fresh()->resolution->value)->toBe('adjust');
    expect($storage->quantityOf($product->id))->toBe(10);
});

it('resolves a credit breach by raising the limit', function () {
    $owner = rrOwner();
    $customer = Customer::create(['name' => 'On Account', 'phone_number' => '0999', 'credit_limit' => 100]);
    $invoice = Invoice::factory()->create(['invocable_id' => $customer->id, 'invocable_type' => Customer::class]);

    $breach = CreditBreachFlag::create([
        'tenant_id' => $customer->tenant_id,
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'credit_limit' => 10000,
        'balance_after' => 50000,
        'occurred_at' => now(),
    ]);
    $item = rrRaise($breach, ReconciliationType::CreditBreach);

    $this->actingAs($owner)
        ->post(route('reconciliation.resolve', $item->id), ['resolution' => 'raise_limit', 'credit_limit' => 10000])
        ->assertRedirect();

    expect($item->fresh()->status)->toBe('resolved');
    expect((int) $customer->fresh()->getRawOriginal('credit_limit'))->toBe(1000000);
});

it('resolves a session variance by acknowledging it', function () {
    $owner = rrOwner();
    $variance = SessionVariance::factory()->create();
    $item = rrRaise($variance, ReconciliationType::SessionVariance);

    $this->actingAs($owner)
        ->post(route('reconciliation.resolve', $item->id), ['resolution' => 'acknowledge', 'note' => 'ok'])
        ->assertRedirect();

    expect($item->fresh()->status)->toBe('resolved');
    expect($item->fresh()->resolution->value)->toBe('acknowledge');
});

it('rejects a resolution that is not allowed for the item type', function () {
    $owner = rrOwner();
    $storage = rrStorage();
    $product = Product::create(['name' => 'Cola', 'cost' => 5, 'price' => 10, 'currency' => 'SDG']);
    $invoice = Invoice::factory()->create(['invocable_id' => $owner->id, 'invocable_type' => User::class]);
    $oversell = OversellReconciliation::create([
        'tenant_id' => $storage->tenant_id,
        'storage_id' => $storage->id,
        'product_id' => $product->id,
        'invoice_id' => $invoice->id,
        'oversold_qty' => 1,
        'on_hand_before' => 0,
        'occurred_at' => now(),
    ]);
    $item = rrRaise($oversell, ReconciliationType::Oversell);

    // `collect` belongs to credit-breach, not oversell.
    $this->actingAs($owner)
        ->post(route('reconciliation.resolve', $item->id), ['resolution' => 'collect'])
        ->assertStatus(422);

    expect($item->fresh()->status)->toBe('open');
});
