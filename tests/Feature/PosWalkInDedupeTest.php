<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);

    // Two system walk-ins — the seeded (localized) one and a checkout-created duplicate.
    $this->canonical = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'عميل عابر', 'is_system' => true]);
    $this->duplicate = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Walk-in Customer', 'is_system' => true]);

    $this->invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'invocable_id' => $this->duplicate->id,
        'invocable_type' => Customer::class,
    ]);
});

function runWalkInDedupe(): void
{
    (require database_path('migrations/2026_07_21_012358_dedupe_walk_in_customers.php'))->up();
}

test('it merges duplicate walk-in customers and reassigns their invoices', function () {
    runWalkInDedupe();

    expect(Customer::where('is_system', true)->count())->toBe(1)
        ->and(Customer::where('is_system', true)->first()->id)->toBe($this->canonical->id)
        ->and($this->invoice->fresh()->invocable_id)->toBe($this->canonical->id);
});

test('it leaves a single walk-in untouched and is idempotent', function () {
    runWalkInDedupe();
    runWalkInDedupe();

    expect(Customer::where('is_system', true)->count())->toBe(1)
        ->and($this->invoice->fresh()->invocable_id)->toBe($this->canonical->id);
});
