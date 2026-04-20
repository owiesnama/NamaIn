<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Preference;
use App\Models\Tenant;
use App\Models\Transaction;
use Database\Seeders\DashboardExampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard example seeder creates demo tenant dashboard data', function () {
    $this->seed(DashboardExampleSeeder::class);

    $tenant = Tenant::where('slug', 'demo')->first();

    expect($tenant)->not->toBeNull();

    expect(Preference::where('tenant_id', $tenant->id)->where('key', 'language')->exists())->toBeTrue();
    expect(Customer::where('tenant_id', $tenant->id)->exists())->toBeTrue();
    expect(Invoice::where('tenant_id', $tenant->id)->exists())->toBeTrue();
    expect(Transaction::where('tenant_id', $tenant->id)->exists())->toBeTrue();
    expect(Expense::where('tenant_id', $tenant->id)->exists())->toBeTrue();
});
