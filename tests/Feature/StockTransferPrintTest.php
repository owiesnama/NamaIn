<?php

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTransfer(): StockTransfer
{
    $fromStorage = Storage::factory()->create(['name' => 'Source Warehouse']);
    $toStorage = Storage::factory()->create(['name' => 'Destination Warehouse']);
    $product = Product::factory()->create(['name' => 'Transferred Widget']);

    $transfer = StockTransfer::factory()->transferred()->create([
        'from_storage_id' => $fromStorage->id,
        'to_storage_id' => $toStorage->id,
    ]);

    StockTransferLine::factory()->create([
        'stock_transfer_id' => $transfer->id,
        'product_id' => $product->id,
        'quantity' => 7,
    ]);

    return $transfer;
}

test('authorized user can view the stock transfer print page', function () {
    actingAsTenantUser(role: 'owner');

    $transfer = makeTransfer();

    $response = $this->get(route('stock-transfers.print', $transfer));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('StockTransfers/Print')
        ->has('transfer', fn ($prop) => $prop
            ->where('id', $transfer->id)
            ->where('from_storage.name', 'Source Warehouse')
            ->where('to_storage.name', 'Destination Warehouse')
            ->has('creator')
            ->has('lines', 1)
            ->has('lines.0', fn ($line) => $line
                ->where('quantity', 7)
                ->where('product.name', 'Transferred Widget')
                ->etc()
            )
            ->etc()
        )
    );
});

test('user without inventory transfer permission cannot view the print page', function () {
    actingAsTenantUser(role: 'staff');

    $transfer = makeTransfer();

    $this->get(route('stock-transfers.print', $transfer))->assertForbidden();
});

test('unauthenticated user cannot view the print page', function () {
    actingAsTenantUser(role: 'owner');

    $transfer = makeTransfer();

    auth()->logout();

    $this->get(route('stock-transfers.print', $transfer))->assertRedirect();
});

test('a transfer from another tenant is not accessible', function () {
    actingAsTenantUser(role: 'owner');

    $otherTenant = Tenant::factory()->create();

    $foreignTransfer = StockTransfer::factory()->create([
        'tenant_id' => $otherTenant->id,
        'from_storage_id' => Storage::factory()->create()->id,
        'to_storage_id' => Storage::factory()->create()->id,
        'created_by' => User::factory()->create()->id,
    ]);

    $this->get(route('stock-transfers.print', $foreignTransfer))->assertNotFound();
});
