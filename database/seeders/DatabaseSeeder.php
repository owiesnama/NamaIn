<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Category;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Preference;
use App\Models\Product;
use App\Models\RecurringExpense;
use App\Models\Stock;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // 1. Core Data
        $banks = Bank::factory(5)->create();
        $categories = Category::factory(10)->create();
        $storages = Storage::factory(3)->create();
        Preference::factory(10)->create();

        // 2. Users
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        User::factory(5)->create();

        // 3. Products & Stock
        $products = Product::factory(20)->create()->each(function ($product) use ($categories) {
            // Attach categories (Polymorphic)
            $product->categories()->attach($categories->random(fake()->numberBetween(1, 2))->pluck('id'));

            // Create Units for the Product
            Unit::factory(2)->create([
                'product_id' => $product->id,
            ]);
        });

        $products->each(function ($product) use ($storages) {
            // Attach stock to storages
            $product->stock()->attach(
                $storages->random()->id,
                ['quantity' => fake()->numberBetween(10, 1000)]
            );
        });

        // 4. Customers & Sales
        $customers = Customer::factory(15)->create()->each(function ($customer) use ($banks) {
            // Create Invoices for Customer
            Invoice::factory(3)->create([
                'invocable_id' => $customer->id,
                'invocable_type' => Customer::class,
            ])->each(function ($invoice) {
                // Create Payments for Invoice
                Payment::factory(fake()->numberBetween(1, 2))->create([
                    'invoice_id' => $invoice->id,
                ]);

                // Create Transactions for Invoice
                Transaction::factory(fake()->numberBetween(1, 3))->create([
                    'invoice_id' => $invoice->id,
                ]);
            });

            // Create Cheques for Customer
            Cheque::factory(2)->create([
                'chequeable_id' => $customer->id,
                'chequeable_type' => Customer::class,
                'bank_id' => $banks->random()->id,
            ]);
        });

        // 5. Suppliers & Purchases
        $suppliers = Supplier::factory(10)->create()->each(function ($supplier) use ($banks) {
            // Create Invoices for Supplier (Purchases)
            Invoice::factory(2)->create([
                'invocable_id' => $supplier->id,
                'invocable_type' => Supplier::class,
            ])->each(function ($invoice) {
                Payment::factory(fake()->numberBetween(0, 1))->create([
                    'invoice_id' => $invoice->id,
                ]);
            });

            // Create Cheques for Supplier
            Cheque::factory(1)->create([
                'chequeable_id' => $supplier->id,
                'chequeable_type' => Supplier::class,
                'bank_id' => $banks->random()->id,
            ]);
        });

        // 6. Expenses
        Expense::factory(15)->create()->each(function ($expense) use ($categories) {
            $expense->categories()->attach($categories->random(fake()->numberBetween(1, 2))->pluck('id'));
        });

        RecurringExpense::factory(5)->create()->each(function ($recurring) use ($categories) {
            $recurring->categories()->attach($categories->random(fake()->numberBetween(1, 2))->pluck('id'));
        });
    }
}
