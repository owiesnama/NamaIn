<?php

use App\Models\Product;
use App\Models\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('authorized user can view reports index', function () {
    actingAsTenantUser(role: 'owner');

    $response = $this->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Reports/Index'));
});

test('unauthorized user receives 403 on reports index', function () {
    actingAsTenantUser(role: 'staff');

    $response = $this->get(route('reports.index'));

    $response->assertForbidden();
});

test('a non-owner role holding the reports permission can view reports', function () {
    actingAsTenantUser(role: 'manager');

    $this->get(route('reports.index'))->assertOk();
    $this->get(route('reports.sales'))->assertOk();
});

test('authorized user can view each report page', function () {
    actingAsTenantUser(role: 'owner');

    $routes = [
        'reports.sales' => 'Reports/Sales',
        'reports.purchases' => 'Reports/Purchase',
        'reports.pos-sessions' => 'Reports/PosSession',
        'reports.pnl' => 'Reports/ProfitAndLoss',
        'reports.inventory-valuation' => 'Reports/InventoryValuation',
        'reports.expenses' => 'Reports/ExpenseSummary',
        'reports.customer-aging' => 'Reports/CustomerAging',
        'reports.supplier-aging' => 'Reports/SupplierAging',
        'reports.treasury' => 'Reports/TreasuryReconciliation',
    ];

    foreach ($routes as $routeName => $component) {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component($component));
    }
});

test('unauthorized user receives 403 on individual report pages', function () {
    actingAsTenantUser(role: 'staff');

    $routes = [
        'reports.sales',
        'reports.purchases',
        'reports.pos-sessions',
        'reports.pnl',
        'reports.inventory-valuation',
        'reports.expenses',
        'reports.customer-aging',
        'reports.supplier-aging',
        'reports.treasury',
    ];

    foreach ($routes as $routeName) {
        $response = $this->get(route($routeName));

        $response->assertForbidden();
    }
});

test('sales report returns expected data shape', function () {
    actingAsTenantUser(role: 'owner');

    $response = $this->get(route('reports.sales'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('data')
        ->has('summary')
        ->has('filters')
        ->has('presets')
    );
});

test('profit and loss report returns expected data shape', function () {
    actingAsTenantUser(role: 'owner');

    $response = $this->get(route('reports.pnl'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('data')
        ->has('summary')
        ->has('presets')
    );
});

test('reports accept date preset filter', function () {
    actingAsTenantUser(role: 'owner');

    $response = $this->get(route('reports.sales', ['preset' => 'last_month']));

    $response->assertOk();
});

test('reports accept custom date range filter', function () {
    actingAsTenantUser(role: 'owner');

    $response = $this->get(route('reports.sales', [
        'from_date' => now()->subMonth()->toDateString(),
        'to_date' => now()->toDateString(),
    ]));

    $response->assertOk();
});

test('inventory valuation report exposes product, storage, and average cost keys', function () {
    actingAsTenantUser(role: 'owner');

    $storage = Storage::factory()->create(['name' => 'Main Warehouse']);
    $product = Product::factory()->create([
        'name' => 'Valuation Widget',
        'cost' => 50,
        'average_cost' => 50,
    ]);
    $storage->stock()->attach($product->id, ['quantity' => 10, 'public_id' => strtolower((string) Str::ulid())]);

    $response = $this->get(route('reports.inventory-valuation'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/InventoryValuation')
        ->has('data', 1)
        ->has('data.0', fn ($row) => $row
            ->where('product_name', 'Valuation Widget')
            ->where('storage_name', 'Main Warehouse')
            ->where('average_cost', 50)
            ->etc()
        )
    );
});
