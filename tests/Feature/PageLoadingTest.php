<?php

use App\Enums\StorageType;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('all major index and create pages load successfully', function (string $route) {
    $user = User::factory()->create();
    $this->actingAs($user); // This will handle tenant setup via TestCase::ensureTenantContext
    $tenant = $user->fresh()->currentTenant;

    // Ensure POS can load if that route is requested
    if ($route === '/pos') {
        Storage::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => StorageType::SALE_POINT,
        ]);
    }

    $response = $this->get($route);

    $response->assertStatus(200);
    $response->assertInertia();
})->with([
    '/dashboard',
    '/customers',
    '/suppliers',
    '/products',
    '/sales',
    '/sales/create',
    '/purchases',
    '/purchases/create',
    '/storages',
    '/expenses',
    '/expenses/create',
    '/recurring-expenses',
    '/recurring-expenses/create',
    '/payments',
    '/payments/create',
    '/cheques',
    '/cheques/create',
    '/preferences',
    '/pos',
]);

test('invoice show page loads successfully', function () {
    $user = User::factory()->admin()->create();
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);

    $response = $this->actingAs($user)->get("/invoice/show/{$invoice->id}");

    $response->assertStatus(200);
    $response->assertInertia();
});

test('storage show page loads successfully', function () {
    $user = User::factory()->admin()->create();
    $storage = Storage::factory()->create();

    $response = $this->actingAs($user)->get("/storages/{$storage->id}");

    $response->assertStatus(200);
    $response->assertInertia();
});

test('customer account and statement pages load successfully', function () {
    $user = User::factory()->admin()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($user)->get("/customers/{$customer->id}/account")->assertStatus(200)->assertInertia();
    $this->actingAs($user)->get("/customers/{$customer->id}/statement")->assertStatus(200)->assertInertia();
});

test('supplier account and statement pages load successfully', function () {
    $user = User::factory()->admin()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)->get("/suppliers/{$supplier->id}/account")->assertStatus(200)->assertInertia();
    $this->actingAs($user)->get("/suppliers/{$supplier->id}/statement")->assertStatus(200)->assertInertia();
});

test('payment show page loads successfully', function () {
    $user = User::factory()->admin()->create();
    $payment = Payment::factory()->create();

    $response = $this->actingAs($user)->get("/payments/{$payment->id}");

    $response->assertStatus(200);
    $response->assertInertia();
});

test('expense show and edit pages load successfully', function () {
    $user = User::factory()->admin()->create();
    $expense = Expense::factory()->create();

    $this->actingAs($user)->get("/expenses/{$expense->id}")->assertStatus(200)->assertInertia();
    $this->actingAs($user)->get("/expenses/{$expense->id}/edit")->assertStatus(200)->assertInertia();
});
