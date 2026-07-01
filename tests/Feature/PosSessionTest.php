<?php

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Enums\StorageType;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Role;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\Reports\PosSessionReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');
    seedTenantRoles($this->tenant);

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'cashier')->first();

    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
});

function createSalePoint(string $name): Storage
{
    return Storage::factory()->create([
        'tenant_id' => app('currentTenant')->id,
        'type' => StorageType::SALE_POINT,
        'name' => $name,
    ]);
}

function recordDeliveredSale(Storage $storage, Product $product, int $quantity): void
{
    $invoice = Invoice::factory()->create();

    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'storage_id' => $storage->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'base_quantity' => $quantity,
        'price' => 100,
        'delivered' => true,
        'created_at' => now(),
    ]);
}

test('it can open a pos session', function () {
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    expect($session->storage_id)->toBe($this->storage->id);
    expect($session->opened_by)->toBe($this->cashier->id);
    expect($session->opening_float)->toBe(5000);
    expect($this->storage->fresh()->active_session_id)->toBe($session->id);
});

test('it cannot open multiple sessions for the same storage', function () {
    app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    expect(fn () => app(OpenPosSessionAction::class)->execute($this->storage, 6000, $this->owner))
        ->toThrow(DomainException::class, 'Storage already has an active POS session.');
});

test('it can close a pos session', function () {
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    app(ClosePosSessionAction::class)->execute($session, 12000, $this->owner);

    $session->refresh();
    expect($session->closed_at)->not->toBeNull();
    expect($session->closed_by)->toBe($this->owner->id);
    expect($session->closing_float)->toBe(12000);
    expect($this->storage->fresh()->active_session_id)->toBeNull();
});

test('it calculates variance correctly', function () {
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    $session->update(['closing_float' => 15000]); // Expected 5000 (no sales)

    expect($session->variance())->toBe(10000);
});

test('cash sales total converts invoice totals to cents and ignores non-cash invoices', function () {
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    Invoice::factory()->create([
        'pos_session_id' => $session->id,
        'payment_method' => 'cash',
        'total' => 150.75,
    ]);

    Invoice::factory()->create([
        'pos_session_id' => $session->id,
        'payment_method' => 'credit',
        'total' => 999,
    ]);

    expect($session->cashSalesTotal())->toBe(15075)
        ->and($session->expectedClosingFloat())->toBe(20075);
});

test('variance compares the closing float against opening float plus cash sales in cents', function () {
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    Invoice::factory()->create([
        'pos_session_id' => $session->id,
        'payment_method' => 'cash',
        'total' => 150.75,
    ]);

    $session->update(['closing_float' => 20000]);

    expect($session->variance())->toBe(-75);
});

test('the pos session report converts session floats to major units to match invoice totals', function () {
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    Invoice::factory()->create([
        'pos_session_id' => $session->id,
        'payment_method' => 'cash',
        'total' => 150.75,
    ]);

    app(ClosePosSessionAction::class)->execute($session, 20000, $this->owner);

    $row = collect(app(PosSessionReportQuery::class)->get(now()->subDay(), now()->addDay()))
        ->firstWhere('id', $session->id);

    expect($row['opening_float'])->toEqual(50.00)
        ->and($row['cash_sales'])->toEqual(150.75)
        ->and($row['expected_close'])->toEqual(200.75)
        ->and($row['closing_float'])->toEqual(200.00)
        ->and($row['variance'])->toEqual(-0.75);

    $summary = app(PosSessionReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect($summary['total_cash_sales'])->toEqual(150.75)
        ->and($summary['total_variance'])->toEqual(-0.75);
});

test('it can open sessions on two different sale points independently', function () {
    $first = createSalePoint('Register One');
    $second = createSalePoint('Register Two');

    $firstSession = app(OpenPosSessionAction::class)->execute($first, 5000, $this->cashier);
    $secondSession = app(OpenPosSessionAction::class)->execute($second, 7000, $this->cashier);

    expect($firstSession->storage_id)->toBe($first->id);
    expect($secondSession->storage_id)->toBe($second->id);
    expect($first->fresh()->active_session_id)->toBe($firstSession->id);
    expect($second->fresh()->active_session_id)->toBe($secondSession->id);
});

test('show passes hot products ordered by quantity sold and scoped to the sale point', function () {
    $salePoint = createSalePoint('Hot Register');
    $otherSalePoint = createSalePoint('Other Register');

    $topSeller = Product::factory()->create(['name' => 'Top Seller']);
    $runnerUp = Product::factory()->create(['name' => 'Runner Up']);
    $otherStorageProduct = Product::factory()->create(['name' => 'Elsewhere Only']);

    recordDeliveredSale($salePoint, $topSeller, 30);
    recordDeliveredSale($salePoint, $runnerUp, 10);
    recordDeliveredSale($otherSalePoint, $otherStorageProduct, 99);

    app(OpenPosSessionAction::class)->execute($salePoint, 5000, $this->cashier);

    $this->actingAs($this->owner)
        ->get(route('pos.index', ['storage_id' => $salePoint->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Session')
            ->where('hotProducts.0.id', $topSeller->id)
            ->where('hotProducts.1.id', $runnerUp->id)
            ->where('hotProducts', fn ($hot) => collect($hot)->pluck('id')->doesntContain($otherStorageProduct->id))
        );
});

test('show renders the selected sale point with its own products', function () {
    $first = createSalePoint('First Register');
    $second = createSalePoint('Second Register');

    $product = Product::factory()->create(['name' => 'Stocked Product']);
    $second->addStock($product, 12, 'manual_add', actor: $this->owner);

    app(OpenPosSessionAction::class)->execute($second, 5000, $this->cashier);

    $this->actingAs($this->owner)
        ->get(route('pos.index', ['storage_id' => $second->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Session')
            ->where('session.storage_id', $second->id)
            ->where('selectedStorageId', $second->id)
            ->where('initialProducts.data.0.id', $product->id)
            ->where('initialProducts.data.0.sale_point_qty', 12)
        );
});

test('show falls back to the first sale point for a non sale point storage', function () {
    $salePoint = createSalePoint('Valid Register');
    $warehouse = Storage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => StorageType::WAREHOUSE,
    ]);

    $this->actingAs($this->owner)
        ->get(route('pos.index', ['storage_id' => $warehouse->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Open')
            ->where('storage.id', $salePoint->id)
        );
});

test('show falls back to the first sale point for another tenants storage', function () {
    $salePoint = createSalePoint('My Register');

    $otherTenant = Tenant::factory()->create();
    $foreignSalePoint = Storage::factory()->create([
        'tenant_id' => $otherTenant->id,
        'type' => StorageType::SALE_POINT,
    ]);

    $this->actingAs($this->owner)
        ->get(route('pos.index', ['storage_id' => $foreignSalePoint->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Open')
            ->where('storage.id', $salePoint->id)
        );
});
