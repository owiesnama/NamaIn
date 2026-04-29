<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

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
