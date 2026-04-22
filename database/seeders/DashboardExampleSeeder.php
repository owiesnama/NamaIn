<?php

namespace Database\Seeders;

use App\Enums\ChequeStatus;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Preference;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DashboardExampleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo Organization', 'is_active' => true],
        );

        app()->instance('currentTenant', $tenant);

        $owner = User::firstOrCreate(
            ['email' => 'demo-owner@namain.test'],
            ['name' => 'Demo Owner', 'password' => bcrypt('password'), 'current_tenant_id' => $tenant->id],
        );

        if (! $owner->belongsToTenant($tenant)) {
            $tenant->users()->attach($owner->id, ['role' => 'owner']);
        }

        if ($owner->current_tenant_id !== $tenant->id) {
            $owner->update(['current_tenant_id' => $tenant->id]);
        }

        Preference::updateOrCreate(['key' => 'language'], ['value' => 'en']);
        Preference::updateOrCreate(['key' => 'currency'], ['value' => 'USD']);

        $storage = Storage::firstOrCreate(
            ['name' => 'Demo Main Storage', 'tenant_id' => $tenant->id],
            ['address' => 'Demo Warehouse'],
        );

        $product = Product::firstOrCreate(
            ['name' => 'Demo Product', 'tenant_id' => $tenant->id],
            ['cost' => 125, 'expire_date' => now()->addYear()],
        );

        $product->stock()->syncWithoutDetaching([
            $storage->id => ['quantity' => 60],
        ]);

        $customer = Customer::firstOrCreate(
            ['name' => 'Demo Customer', 'tenant_id' => $tenant->id],
            ['address' => 'Customer Address', 'phone_number' => '0000000000'],
        );

        $supplier = Supplier::firstOrCreate(
            ['name' => 'Demo Supplier', 'tenant_id' => $tenant->id],
            ['address' => 'Supplier Address', 'phone_number' => '1111111111'],
        );

        $customerInvoice = Invoice::firstOrCreate(
            ['serial_number' => 'DEMO-SALE-001', 'tenant_id' => $tenant->id],
            [
                'invocable_id' => $customer->id,
                'invocable_type' => Customer::class,
                'total' => 2500,
                'discount' => 100,
                'paid_amount' => 500,
                'currency' => 'USD',
                'status' => InvoiceStatus::Delivered,
            ],
        );

        Transaction::firstOrCreate(
            [
                'invoice_id' => $customerInvoice->id,
                'product_id' => $product->id,
                'storage_id' => $storage->id,
                'tenant_id' => $tenant->id,
            ],
            [
                'quantity' => 10,
                'base_quantity' => 10,
                'price' => 250,
                'delivered' => true,
                'currency' => 'USD',
            ],
        );

        $supplierInvoice = Invoice::firstOrCreate(
            ['serial_number' => 'DEMO-PUR-001', 'tenant_id' => $tenant->id],
            [
                'invocable_id' => $supplier->id,
                'invocable_type' => Supplier::class,
                'total' => 1500,
                'discount' => 0,
                'paid_amount' => 300,
                'currency' => 'USD',
                'status' => InvoiceStatus::Delivered,
            ],
        );

        Transaction::firstOrCreate(
            [
                'invoice_id' => $supplierInvoice->id,
                'product_id' => $product->id,
                'storage_id' => $storage->id,
                'tenant_id' => $tenant->id,
                'description' => 'Demo purchase',
            ],
            [
                'quantity' => 20,
                'base_quantity' => 20,
                'price' => 75,
                'delivered' => true,
                'currency' => 'USD',
            ],
        );

        Expense::firstOrCreate(
            ['title' => 'Demo Rent', 'tenant_id' => $tenant->id],
            [
                'amount' => 300,
                'currency' => 'USD',
                'expensed_at' => now()->subDays(3),
                'created_by' => $owner->id,
                'status' => ExpenseStatus::Approved,
            ],
        );

        Cheque::firstOrCreate(
            ['reference_number' => 'DEMO-CHQ-001', 'tenant_id' => $tenant->id],
            [
                'chequeable_id' => $customer->id,
                'chequeable_type' => Customer::class,
                'amount' => 800,
                'type' => 1,
                'bank' => 'Demo Bank',
                'status' => ChequeStatus::Issued,
                'due' => now()->addDays(5),
            ],
        );

        $this->command?->info('Dashboard example data seeded for demo.namain.test');
    }
}
