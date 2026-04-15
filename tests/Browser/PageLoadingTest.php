<?php

namespace Tests\Browser;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PageLoadingTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_all_pages_load_and_content_is_visible(): void
    {
        $user = User::factory()->admin()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);

            // Dashboard
            $browser->visit('/dashboard')
                ->assertPathIs('/dashboard')
                ->waitForText(__('Dashboard'))
                ->assertSee(__('Gross Profit'))
                ->assertSee(__('Total Revenue'))
                ->assertSee(__('Receivables'))
                ->assertSee(__('Payables'));

            // Customers
            $browser->visit('/customers')
                ->assertPathIs('/customers')
                ->waitForText(__('Customers'))
                ->assertSee('+ '.__('Add New').' '.__('Customer'))
                ->assertVisible('input[placeholder*="'.__('Search').'"]');

            // Suppliers
            $browser->visit('/suppliers')
                ->assertPathIs('/suppliers')
                ->waitForText(__('Suppliers'))
                ->assertSee('+ '.__('Add New Supplier'));

            // Products
            $browser->visit('/products')
                ->assertPathIs('/products')
                ->waitForText(__('Products'))
                ->assertSee('+ '.__('Add New Product'));

            // Sales
            $browser->visit('/sales')
                ->assertPathIs('/sales')
                ->waitForText(__('Sales'))
                ->assertSee('+ '.__('Add New Invoice'));

            // Purchases
            $browser->visit('/purchases')
                ->assertPathIs('/purchases')
                ->waitForText(__('Purchases'))
                ->assertSee('+ '.__('Add New Invoice'));

            // Storages
            $browser->visit('/storages')
                ->assertPathIs('/storages')
                ->waitForText(__('Storages'))
                ->assertSee('+ '.__('Add New Storage'));

            // Expenses
            $browser->visit('/expenses')
                ->assertPathIs('/expenses')
                ->waitForText(__('Expenses'))
                ->assertSee('+ '.__('Record Expense'));

            // Recurring Expenses
            $browser->visit('/recurring-expenses')
                ->assertPathIs('/recurring-expenses')
                ->waitForText(__('Recurring Expense Templates'))
                ->assertSee('+ '.__('New Template'));

            // Payments
            $browser->visit('/payments')
                ->assertPathIs('/payments')
                ->waitForText(__('Payments'))
                ->assertSee('+ '.__('Record Payment'));

            // Cheques
            $browser->visit('/cheques')
                ->assertPathIs('/cheques')
                ->waitForText(__('Cheques'))
                ->assertSee('+ '.__('Register Cheque'));

            // Preferences
            $browser->visit('/preferences')
                ->assertPathIs('/preferences')
                ->waitForText(__('Application Information'));
        });
    }

    public function test_created_data_is_visible_on_pages(): void
    {
        $user = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['name' => 'Dusk Test Customer']);
        $supplier = Supplier::factory()->create(['name' => 'Dusk Test Supplier']);
        $product = Product::factory()->create(['name' => 'Dusk Test Product']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);

            // Customers
            $browser->visit('/customers')
                ->waitForText('Dusk Test Customer')
                ->assertSee('Dusk Test Customer');

            // Suppliers
            $browser->visit('/suppliers')
                ->waitForText('Dusk Test Supplier')
                ->assertSee('Dusk Test Supplier');

            // Products
            $browser->visit('/products')
                ->waitForText('Dusk Test Product')
                ->assertSee('Dusk Test Product');
        });
    }
}
